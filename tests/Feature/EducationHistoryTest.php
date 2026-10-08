<?php

namespace Tests\Feature;

use App\Services\EducationHistory;
use App\Models\Estadistica;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EducationHistoryTest extends TestCase
{
    public function test_school_history_keeps_missing_years_and_latest_record_without_double_counting(): void
    {
        $records = collect([
            new Estadistica(['id'=>1,'anio'=>2025,'categoria'=>'reprobados','total'=>99]),
            new Estadistica(['id'=>2,'anio'=>2025,'categoria'=>'reprobados','total'=>10,'hombre'=>6,'mujer'=>4]),
            new Estadistica(['id'=>3,'anio'=>2025,'categoria'=>'matricula','total'=>100]),
            new Estadistica(['id'=>4,'anio'=>2024,'categoria'=>'reprobados','total'=>0]),
        ]);
        $rows = collect((new EducationHistory)->school($records))->keyBy('year');
        $this->assertSame(10, $rows[2025]['reprobados']);
        $this->assertSame(10.0, $rows[2025]['tasa']);
        $this->assertSame(0, $rows[2024]['reprobados']);
        $this->assertNull($rows[2024]['tasa']);
        $this->assertNull($rows[2021]['reprobados']);
    }

    public function test_projection_requires_three_observations_and_2025_and_clamps_negative_counts(): void
    {
        $service = new EducationHistory;
        $points = array_map(fn($year)=>['year'=>$year,'total'=>($year-2020)*100],range(2021,2025));
        $this->assertSame(600,$service->forecast($points,'total'));
        $this->assertNull($service->forecast(array_slice($points,0,4),'total'));
        $this->assertNull($service->forecast(array_slice($points,3),'total'));
        $this->assertSame(0,$service->forecast([['year'=>2023,'total'=>30],['year'=>2024,'total'=>15],['year'=>2025,'total'=>0]],'total'));
        $this->assertNull($service->percent(12,10));
        $this->assertNull($service->percent(0,0));
    }

    public function test_department_rates_use_same_schools_and_deduplicate_statistics_and_locations(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        Schema::create('estadisticas', function(Blueprint $t){$t->id();$t->integer('school_id');$t->integer('anio');$t->string('categoria');$t->string('total')->nullable();$t->integer('hombre')->nullable();$t->integer('mujer')->nullable();});
        Schema::create('ubicacions',function(Blueprint $t){$t->id();$t->integer('school_id');$t->string('departamento');});
        DB::table('ubicacions')->insert([['school_id'=>1,'departamento'=>'SANTA CRUZ'],['school_id'=>1,'departamento'=>'SANTA CRUZ'],['school_id'=>2,'departamento'=>'SANTA CRUZ'],['school_id'=>3,'departamento'=>'SANTA CRUZ']]);
        foreach ([[1,'reprobados',999,600,399],[1,'reprobados',10,6,4],[1,'matricula',100,60,40],[2,'reprobados',20,null,null],[3,'matricula',1000,500,500]] as [$school,$category,$total,$men,$women]) {
            DB::table('estadisticas')->insert(['school_id'=>$school,'anio'=>2025,'categoria'=>$category,'total'=>$total,'hombre'=>$men,'mujer'=>$women]);
        }
        $data=(new EducationHistory)->departments();
        $row=$data['SANTA CRUZ'][4];
        $this->assertSame(30,$row['total']);
        $this->assertSame(10.0,$row['tasa']);
        $this->assertSame(100,$row['base_tasa']);
        $this->assertSame(2,$row['colegios']);
        $this->assertFalse($row['sexo_completo']);
        $this->assertSame(20.0,$row['porcentaje_hombres']);
        $this->assertSame(13.33,$row['porcentaje_mujeres']);
        $this->assertNull($data['BENI'][4]['total']);
        $this->assertNull($data['SANTA CRUZ'][5]['total']);
        $this->get('/historia-aplazados')->assertOk()->assertSee('Modo video')->assertSee('Cómo leer la proyección de 2026')
            ->assertSee('HISTORIAL APLAZADOS SANTA CRUZ')->assertSee('APLAZADOS HOMBRES VS. MUJERES — SANTA CRUZ')
            ->assertSee('id="eh-pie-year"', false)->assertSee('id="eh-replay-pie"', false)
            ->assertDontSee('Mujeres · turquesa')->assertDontSee('Hombres · azul');
        DB::table('estadisticas')->where('school_id',2)->where('categoria','reprobados')->update(['hombre'=>0,'mujer'=>20]);
        $complete = (new EducationHistory)->departments()['SANTA CRUZ'][4];
        $this->assertTrue($complete['sexo_completo']);
        $this->assertSame(20.0,$complete['porcentaje_hombres']);
        $this->assertSame(80.0,$complete['porcentaje_mujeres']);
        foreach (range(2023,2024) as $year) {
            DB::table('estadisticas')->insert(['school_id'=>1,'anio'=>$year,'categoria'=>'reprobados','total'=>10,'hombre'=>0,'mujer'=>10]);
            DB::table('estadisticas')->insert(['school_id'=>1,'anio'=>$year,'categoria'=>'matricula','total'=>100,'hombre'=>50,'mujer'=>50]);
        }
        $projected = (new EducationHistory)->departments()['SANTA CRUZ'][5];
        $this->assertSame(2026,$projected['year']);
        $this->assertNotNull($projected['hombres']);
        $this->assertSame($projected['total'],$projected['hombres']+$projected['mujeres']);
        $this->assertNotNull($projected['matricula']);
        $this->travelTo(now()->setDate(2026,10,8));
        DB::table('estadisticas')->insert(['school_id'=>1,'anio'=>2026,'categoria'=>'reprobados','total'=>12,'hombre'=>6,'mujer'=>6]);
        $next = (new EducationHistory)->departments()['SANTA CRUZ'];
        $this->assertSame(2027,end($next)['year']);
        $this->assertTrue(end($next)['projection']);
        $this->get('/historia-aplazados')->assertOk()->assertSee('Cómo leer la proyección de 2027')->assertSee('eh-sex-chart')->assertSee('eh-panorama-sort');
    }
}
