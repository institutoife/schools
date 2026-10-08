<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ReportAccessTest extends TestCase
{
    public function test_guest_is_sent_to_login_and_report_is_remembered(): void
    {
        $response = $this->get(route('reports.access', 'aplazados-provincia'));

        $response->assertRedirect(url('/admin/login'));
        $this->assertSame(
            route('reports.access', 'aplazados-provincia'),
            session('url.intended')
        );
    }

    public function test_authenticated_user_is_sent_to_selected_report(): void
    {
        $user = new User(['name' => 'Prueba', 'email' => 'prueba@ife.bo']);

        $response = $this->actingAs($user)
            ->get(route('reports.access', 'aplazados-provincia'));

        $response->assertRedirect(url('/rankings?tipo=reprobacion&nivel=provincia'));
    }

    public function test_unknown_report_returns_not_found(): void
    {
        $this->get(route('reports.access', 'inexistente'))->assertNotFound();
    }

    public function test_history_direct_url_requires_login_and_preserves_department_and_year(): void
    {
        $url = url('/historia-aplazados?departamento=BENI&anio=2025');
        $this->get($url)->assertRedirect(url('/admin/login'));
        $this->assertSame('/historia-aplazados', parse_url(session('url.intended'), PHP_URL_PATH));
        parse_str(parse_url(session('url.intended'), PHP_URL_QUERY), $query);
        $this->assertEquals(['departamento' => 'BENI', 'anio' => '2025'], $query);
    }

    public function test_history_home_option_requires_login_and_redirects_to_the_report(): void
    {
        $this->get(route('reports.access', 'historial-aplazados'))->assertRedirect(url('/admin/login'));
        $this->actingAs(new User(['name' => 'Prueba', 'email' => 'prueba@ife.bo']));
        $this->get(route('reports.access', 'historial-aplazados'))->assertRedirect(url('/historia-aplazados'));
    }

    public function test_home_exposes_read_only_previews_without_upload_forms(): void
    {
        $html = view('welcome', [
            'search' => '', 'filter' => 'nombre', 'kpis' => ['total' => 0],
            'locationStats' => ['departments' => 0, 'municipalities' => 0, 'districts' => 0],
            'departments' => collect(), 'featuredSchools' => collect(),
        ])->render();
        $this->assertStringContainsString('Historial de aplazados', $html);
        $this->assertStringContainsString(route('reports.access', 'historial-aplazados'), $html);
        $this->assertStringContainsString('Solo lectura; requiere iniciar sesión', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('wire:submit="startJson"', $html);
    }
}
