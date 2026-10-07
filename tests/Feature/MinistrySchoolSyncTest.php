<?php

namespace Tests\Feature;

use App\Services\MinistrySchoolClient;
use App\Services\MinistrySchoolSync;
use App\Services\SchoolExcelImporter;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MinistrySchoolSyncTest extends TestCase
{
    private string $isolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedStorage = storage_path('framework/testing/ministry-'.\Illuminate\Support\Str::random(12));
        File::ensureDirectoryExists($this->isolatedStorage);
        $this->app->useStoragePath($this->isolatedStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->isolatedStorage);
        parent::tearDown();
    }
    public function test_official_ficha_has_administrative_data_and_statistics_through_2025(): void
    {
        $html = file_get_contents(__DIR__.'/../Fixtures/ministry-72470005.html');
        $record = (new MinistrySchoolClient)->parse($html, '72470005');
        $this->assertSame('CANDELARIA', $record['general']['nombre']);
        $this->assertSame('BEYUMA RAMIREZ VICTOR RAUL', $record['general']['director']);
        $this->assertSame('FISCAL', $record['general']['dependencia']);
        $this->assertSame('PANDO', $record['ubicacion']['departamento']);
        $this->assertEquals(-10.9569655935316, $record['ubicacion']['coordenadas']['latitud']);
        $this->assertEquals(-66.6461509466171, $record['ubicacion']['coordenadas']['longitud']);
        $this->assertSame(15, $record['estadisticas']['matricula']['Total'][2025]);
        $this->assertSame(10, $record['estadisticas']['matricula']['Mujer'][2025]);
        $this->assertSame(5, $record['estadisticas']['matricula']['Hombre'][2025]);
        $this->assertCount(4, $record['estadisticas']);
        $this->assertSame([], $record['infraestructura']['servicios']);
        $this->assertSame(0, $record['infraestructura']['ambientes']['talleres']);
        $this->expectException(\RuntimeException::class);
        (new MinistrySchoolClient)->parse($html, '12345678');
    }

    public function test_ficha_without_confirmed_2025_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new MinistrySchoolClient)->parse(str_replace('2025', '2024', file_get_contents(__DIR__.'/../Fixtures/ministry-72470005.html')), '72470005');
    }

    public function test_http_client_uses_the_official_rue_url_and_rejects_http_errors(): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            MinistrySchoolClient::BASE_URL.'72470005' => \Illuminate\Support\Facades\Http::response(file_get_contents(__DIR__.'/../Fixtures/ministry-72470005.html')),
            MinistrySchoolClient::BASE_URL.'12345678' => \Illuminate\Support\Facades\Http::response('No existe ficha', 404),
        ]);
        $this->assertSame('CANDELARIA', (new MinistrySchoolClient)->fetch('72470005')['general']['nombre']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === MinistrySchoolClient::BASE_URL.'72470005');
        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        (new MinistrySchoolClient)->fetch('12345678');
    }

    public function test_system_reads_only_rue_department_and_generates_and_imports_department_json(): void
    {
        $excel = storage_path('framework/testing/ministry-test.xlsx');
        File::ensureDirectoryExists(dirname($excel));
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Código RUE', 'Nombre ignorado', 'Departamento'], ['72470005', '=1/0', 'PANDO']]);
        (new Xlsx($book))->save($excel);
        $sync = new MinistrySchoolSync;
        $id = null;
        try {
            $this->assertSame([['general' => ['codigo_rue' => '72470005'], 'ubicacion' => ['departamento' => 'PANDO']]], (new SchoolExcelImporter)->read($excel));
            $id = $sync->create($excel);
            $record = (new MinistrySchoolClient)->parse(file_get_contents(__DIR__.'/../Fixtures/ministry-72470005.html'), '72470005');
            $this->mock(MinistrySchoolClient::class)->shouldReceive('fetch')->once()->with('72470005')->andReturn($record);
            $this->mock(SchoolExcelImporter::class)->shouldReceive('import')->times(9)->andReturnUsing(function ($rows) {
                if ($rows) {
                    $this->assertSame('CANDELARIA', $rows[0]['general']['nombre']);
                    $this->assertSame(15, $rows[0]['estadisticas']['matricula']['Total'][2025]);
                }
                return ['nuevos' => count($rows), 'actualizados' => 0, 'sin_cambios' => 0];
            });
            $sync->change($id, 'pausar');
            $sync->tick($id);
            $this->assertSame('pausado', $sync->state($id)['estado']);
            $sync->change($id, 'reanudar');
            for ($i = 0; $i < 11; $i++) $sync->tick($id);
            $this->assertSame('completo', $sync->state($id)['estado']);
            $this->assertCount(9, glob($sync->directory($id).'/json/*.json'));
            $this->assertFileExists($sync->archive($id));
        } finally {
            File::delete($excel);
            if ($id) File::deleteDirectory($sync->directory($id));
        }
    }

    public function test_admin_import_page_renders_upload_progress_and_history(): void
    {
        $this->actingAs(new \App\Models\User(['name' => 'Prueba', 'email' => 'test@example.com']));
        \Livewire\Livewire::test(\App\Filament\Pages\MinistrySchoolImport::class)
            ->assertSuccessful()->assertSee('Importaciones guardadas')->assertSee('Cargar Excel e iniciar actualización');
    }

    public function test_failed_fichas_are_reported_and_can_be_retried_without_importing_invented_data(): void
    {
        $excel = storage_path('fuente.xlsx');
        File::put($excel, 'fixture');
        $importer = $this->mock(SchoolExcelImporter::class);
        $importer->shouldReceive('read')->once()->andReturn([['general' => ['codigo_rue' => '72470005'], 'ubicacion' => ['departamento' => 'BENI']]]);
        $importer->shouldReceive('import')->times(9)->with([])->andReturn(['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 0]);
        $this->mock(MinistrySchoolClient::class)->shouldReceive('fetch')->times(3)->andThrow(new \RuntimeException('Ficha no disponible'));
        $sync = new MinistrySchoolSync;
        $id = $sync->create($excel);
        for ($i = 0; $i < 3; $i++) { $sync->tick($id); if ($i < 2) usleep(1100000); }
        for ($i = 0; $i < 10; $i++) $sync->tick($id);
        $state = $sync->state($id);
        $this->assertSame('con_errores', $state['estado']);
        $this->assertSame('error', $state['departamentos']['beni']['colegios']['72470005']['estado']);
        $this->assertSame('Ficha no disponible', $state['departamentos']['beni']['colegios']['72470005']['error']);
        $this->assertSame([], json_decode(File::get($sync->directory($id).'/json/colegios_beni.json'), true));
        $sync->change($id, 'reintentar');
        $state = $sync->state($id);
        $this->assertSame('procesando', $state['estado']);
        $this->assertSame('pendiente', $state['departamentos']['beni']['colegios']['72470005']['estado']);
        $this->assertSame(0, $state['departamentos']['beni']['colegios']['72470005']['intentos']);
    }
}
