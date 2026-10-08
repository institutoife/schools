<?php

namespace Tests\Feature;

use App\Filament\Pages\MinistrySchoolImport;
use App\Models\School;
use App\Models\User;
use App\Services\MinistrySchoolClient;
use App\Services\MinistrySchoolJson;
use App\Services\MinistrySchoolSync;
use App\Services\SchoolExcelImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class MinistrySchoolJsonTest extends TestCase
{
    private string $isolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedStorage = storage_path('framework/testing/json-'.Str::random(12));
        File::ensureDirectoryExists($this->isolatedStorage);
        $this->app->useStoragePath($this->isolatedStorage);
        Http::preventStrayRequests();
        Http::fake();
        $this->mock(MinistrySchoolClient::class)->shouldNotReceive('fetch');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->isolatedStorage);
        parent::tearDown();
    }

    private function record(string $rue = '72470005'): array
    {
        return [
            'general' => ['codigo_rue' => $rue, 'nombre' => 'Colegio actualizado', 'dependencia' => 'FISCAL'],
            'ubicacion' => ['departamento' => 'PANDO'],
            'departamento_clasificacion' => 'PANDO', 'fuente' => ['gestion' => 2025],
            'estadisticas' => ['reprobados' => ['Total' => [2025 => 5], 'Mujer' => [2025 => 2], 'Hombre' => [2025 => 3]]],
            'infraestructura' => [],
        ];
    }

    private function file(array $records, string $name = 'colegios_pando.json'): array
    {
        $path = storage_path(Str::random(12).'.json');
        File::put($path, json_encode($records, JSON_THROW_ON_ERROR));
        return ['path' => $path, 'name' => $name];
    }

    private function finish(MinistrySchoolSync $sync, string $id): array
    {
        for ($i = 0; $i < 20 && $sync->state($id)['estado'] === 'procesando'; $i++) $sync->tick($id);
        return $sync->state($id);
    }

    public function test_json_upload_creates_updates_and_is_repeatable_without_http_or_duplicates(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('schools', function (Blueprint $t) {
            $t->id(); $t->string('codigo_rue')->unique(); $t->string('nombre');
            $t->string('dependencia'); $t->string('director')->nullable(); $t->timestamps();
        });
        Schema::create('ubicacions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('school_id'); $t->string('departamento'); $t->timestamps();
        });
        Schema::create('estadisticas', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('school_id'); $t->string('categoria'); $t->integer('anio');
            $t->integer('total')->nullable(); $t->integer('mujer')->nullable(); $t->integer('hombre')->nullable(); $t->timestamps();
        });
        $existing = School::create(['codigo_rue' => '72470005', 'nombre' => 'Anterior', 'dependencia' => 'FISCAL', 'director' => 'Conservar']);
        School::create(['codigo_rue' => '11111111', 'nombre' => 'Fuera de la carga', 'dependencia' => 'FISCAL']);
        $file = $this->file([$this->record(), $this->record('87654321')]);
        $sync = new MinistrySchoolSync;
        $first = $this->finish($sync, $sync->createFromJson([$file]));
        $this->assertSame('completo', $first['estado']);
        $this->assertSame(['nuevos' => 1, 'actualizados' => 1, 'sin_cambios' => 0], $first['departamentos']['pando']['resultado']);
        $this->assertSame('omitido', $first['departamentos']['beni']['estado']);
        $this->assertSame('Colegio actualizado', $existing->fresh()->nombre);
        $this->assertSame('Conservar', $existing->fresh()->director);
        $again = $this->finish($sync, $sync->createFromJson([$file]));
        $this->assertSame(['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 2], $again['departamentos']['pando']['resultado']);
        $changed = $this->record(); $changed['estadisticas']['reprobados']['Total'][2025] = 0;
        $third = $this->finish($sync, $sync->createFromJson([$this->file([$changed])]));
        $this->assertSame(1, $third['departamentos']['pando']['resultado']['actualizados']);
        $this->assertSame(3, School::count());
        $this->assertSame(2, DB::table('estadisticas')->count());
        $this->assertSame(0, DB::table('estadisticas')->where('school_id', $existing->id)->value('total'));
        Http::assertNothingSent();
    }

    public function test_batches_resume_and_retry_only_the_failed_batch(): void
    {
        $records = [];
        for ($i = 0; $i < 51; $i++) $records[] = $this->record((string) (72470000 + $i));
        $importer = $this->mock(SchoolExcelImporter::class);
        $importer->shouldReceive('import')->once()->withArgs(fn ($rows) => count($rows) === 50)
            ->andReturn(['nuevos' => 50, 'actualizados' => 0, 'sin_cambios' => 0]);
        $importer->shouldReceive('import')->once()->withArgs(fn ($rows) => count($rows) === 1)
            ->andThrow(new \RuntimeException('Fallo temporal de base de datos'));
        $sync = new MinistrySchoolSync;
        $id = $sync->createFromJson([$this->file($records)]);
        $sync->tick($id);
        $this->assertSame(50, $sync->state($id)['departamentos']['pando']['importados']);
        $sync->change($id, 'pausar'); $sync->tick($id);
        $this->assertSame('pausado', $sync->state($id)['estado']);
        $sync->change($id, 'reanudar');
        $failed = $this->finish($sync, $id);
        $this->assertSame('con_errores', $failed['estado']);
        $this->assertSame(50, $failed['departamentos']['pando']['importados']);
        $importer->shouldReceive('import')->once()->withArgs(fn ($rows) => count($rows) === 1 && $rows[0]['general']['codigo_rue'] === '72470050')
            ->andReturn(['nuevos' => 1, 'actualizados' => 0, 'sin_cambios' => 0]);
        $sync->change($id, 'reintentar');
        $state = $this->finish($sync, $id);
        $this->assertSame('completo', $state['estado']);
        $this->assertSame(51, $state['departamentos']['pando']['resultado']['nuevos']);
        Http::assertNothingSent();
    }

    public function test_downloaded_nine_department_zip_can_be_uploaded_without_extraction(): void
    {
        $sync = new MinistrySchoolSync;
        $this->mock(SchoolExcelImporter::class)->shouldReceive('import')->once()->andReturn(['nuevos' => 1, 'actualizados' => 0, 'sin_cambios' => 0]);
        $id = $sync->createFromJson([$this->file([$this->record()])]);
        $this->finish($sync, $id);
        $zip = $sync->archive($id);
        $reloaded = $sync->state($sync->createFromJson([['path' => $zip, 'name' => 'descarga-anual.zip']]));
        $this->assertCount(9, $reloaded['departamentos']);
        $this->assertCount(1, $reloaded['departamentos']['pando']['colegios']);
        $this->assertSame('json', $reloaded['origen']);
        Http::assertNothingSent();
    }

    public function test_all_files_are_validated_before_any_work_is_registered(): void
    {
        $this->mock(SchoolExcelImporter::class)->shouldNotReceive('import');
        $invalid = $this->record('87654321'); $invalid['fuente']['gestion'] = 2024;
        $invalid['departamento_clasificacion'] = 'BENI';
        try {
            (new MinistrySchoolSync)->createFromJson([$this->file([$this->record()]), $this->file([$invalid], 'colegios_beni.json')]);
            $this->fail('Debe rechazar la gestión incorrecta');
        } catch (\RuntimeException $e) { $this->assertStringContainsString('colegios_beni.json', $e->getMessage()); }
        $this->assertSame([], (new MinistrySchoolSync)->history());
    }

    public function test_duplicate_rue_and_old_excel_only_json_are_rejected(): void
    {
        $json = new MinistrySchoolJson;
        $other = $this->record(); $other['departamento_clasificacion'] = 'BENI';
        try {
            $json->readFiles([$this->file([$this->record()]), $this->file([$other], 'colegios_beni.json')]);
            $this->fail('Debe rechazar un RUE repetido');
        } catch (\RuntimeException $e) { $this->assertStringContainsString('RUE repetido', $e->getMessage()); }
        $this->expectException(\RuntimeException::class);
        $json->readFiles([$this->file([['general' => ['codigo_rue' => '72470005'], 'ubicacion' => ['departamento' => 'PANDO']]])]);
    }

    public function test_zip_with_traversal_paths_is_rejected(): void
    {
        $path = storage_path('invalido.zip');
        $zip = new ZipArchive; $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('../colegios_pando.json', json_encode([$this->record()])); $zip->close();
        $this->expectException(\RuntimeException::class);
        (new MinistrySchoolJson)->readFiles([['path' => $path, 'name' => 'invalido.zip']]);
    }

    public function test_admin_accepts_json_upload_independently_of_excel_form(): void
    {
        Storage::fake('local');
        $this->actingAs(new User(['name' => 'Prueba', 'email' => 'test@example.com']));
        $upload = UploadedFile::fake()->createWithContent('colegios_pando.json', json_encode([$this->record()]));
        $page = Livewire::test(MinistrySchoolImport::class)
            ->assertSee('Importar JSON ya descargados')
            ->fillForm(['archivos' => [$upload]], 'jsonForm')
            ->call('startJson')->assertHasNoFormErrors();
        $id = $page->get('importId');
        $this->assertNotNull($id);
        $this->assertSame('json', (new MinistrySchoolSync)->state($id)['origen']);
        $page->assertSee('JSON cargados')->assertSee('No cargado');
        Http::assertNothingSent();
    }

    public function test_console_uses_the_same_json_validation_as_the_panel(): void
    {
        $directory = storage_path('json'); File::ensureDirectoryExists($directory);
        foreach (SchoolExcelImporter::DEPARTMENTS as $slug) {
            File::put($directory.'/colegios_'.$slug.'.json', json_encode($slug === 'pando' ? [$this->record()] : []));
        }
        $this->mock(SchoolExcelImporter::class)->shouldReceive('import')->once()->withArgs(fn ($rows) => count($rows) === 1)
            ->andReturn(['nuevos' => 1, 'actualizados' => 0, 'sin_cambios' => 0]);
        $this->artisan('schools:import-json', ['directorio' => $directory])->assertSuccessful();
        Http::assertNothingSent();
    }
}
