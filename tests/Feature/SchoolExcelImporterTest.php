<?php

namespace Tests\Feature;

use App\Services\SchoolExcelImporter;
use App\Models\School;
use App\Models\Ubicacion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolExcelImporterTest extends TestCase
{
    public function test_import_creates_updates_preserves_missing_fields_and_is_idempotent(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('schools', function (Blueprint $t) {
            $t->id(); $t->string('codigo_rue')->unique(); $t->string('nombre');
            $t->string('director')->nullable(); $t->string('url_ficha')->nullable(); $t->timestamps();
        });
        Schema::create('ubicacions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('school_id'); $t->string('departamento');
            $t->string('municipio')->nullable(); $t->decimal('latitud', 10, 8)->nullable();
            $t->decimal('longitud', 11, 8)->nullable(); $t->timestamps();
        });
        Schema::create('estadisticas', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('school_id'); $t->string('categoria'); $t->integer('anio');
            $t->string('total')->nullable(); $t->integer('mujer')->nullable(); $t->integer('hombre')->nullable(); $t->timestamps();
        });
        Schema::create('servicios', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('school_id'); $t->boolean('agua')->nullable(); $t->timestamps();
        });
        Schema::create('ambientes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('school_id'); $t->integer('aulas')->nullable(); $t->timestamps();
        });
        $school = School::create(['codigo_rue' => '12345678', 'nombre' => 'Anterior', 'director' => 'Conservar']);
        Ubicacion::create(['school_id' => $school->id, 'departamento' => 'LA PAZ', 'latitud' => '-16.12345678']);
        $records = [
            ['general' => ['codigo_rue' => '12345678', 'nombre' => 'Actualizado'], 'ubicacion' => ['departamento' => 'LA PAZ']],
            ['general' => ['codigo_rue' => '87654321', 'nombre' => 'Nuevo'], 'ubicacion' => ['departamento' => 'BENI', 'coordenadas' => ['latitud' => -13.730699, 'longitud' => -65.396691]]],
        ];
        $records[1]['estadisticas'] = ['matricula' => ['Total' => [2025 => 15, 2026 => 99], 'Mujer' => [2025 => 10], 'Hombre' => [2025 => 5]]];
        $records[1]['infraestructura'] = ['servicios' => ['agua' => false], 'ambientes' => ['aulas' => 0]];
        $importer = new SchoolExcelImporter;
        $this->assertSame(['nuevos' => 1, 'actualizados' => 1, 'sin_cambios' => 0], $importer->import($records));
        $this->assertSame('Conservar', $school->fresh()->director);
        $this->assertEquals(-16.12345678, $school->fresh()->ubicacion->latitud);
        $this->assertSame(['nuevos' => 0, 'actualizados' => 0, 'sin_cambios' => 2], $importer->import($records));
        $this->assertSame(2, School::count());
        $this->assertSame(2, Ubicacion::count());
        $this->assertSame(1, \App\Models\Estadistica::count());
        $this->assertEquals(15, \App\Models\Estadistica::first()->total);
        $this->assertSame(1, \App\Models\Servicio::count());
        $this->assertSame(1, \App\Models\Ambiente::count());
        $records[1]['estadisticas']['matricula']['Total'][2025] = 16;
        $this->assertSame(['nuevos' => 0, 'actualizados' => 1, 'sin_cambios' => 1], $importer->import($records));
        $this->assertSame(1, \App\Models\Estadistica::count());
        $this->assertEquals(16, \App\Models\Estadistica::first()->total);
        $records[1]['ubicacion']['departamento'] = null;
        $records[0]['general']['nombre'] = 'Debe revertirse';
        try { $importer->import($records); $this->fail('Debe fallar la transacción'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame('Actualizado', $school->fresh()->nombre); }
    }
}
