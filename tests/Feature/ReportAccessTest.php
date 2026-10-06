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
}
