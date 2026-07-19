<?php

namespace Tests\Feature;

use App\Listeners\InvalidateOtherSessions;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_iniciar_sesion_cierra_las_demas_sesiones_del_mismo_usuario(): void
    {
        // El listener solo actúa con SESSION_DRIVER=database, que es lo que
        // usa producción — el entorno de pruebas usa "array" por defecto.
        config(['session.driver' => 'database']);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'session-dispositivo-viejo', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => now()->timestamp],
            ['id' => 'session-otro-usuario', 'user_id' => User::factory()->create()->id, 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        Session::setId('session-dispositivo-nuevo');

        (new InvalidateOtherSessions)->handle(new Login('web', $user, false));

        $this->assertDatabaseMissing('sessions', ['id' => 'session-dispositivo-viejo']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-otro-usuario']);
    }

    public function test_login_real_por_http_cierra_la_sesion_anterior_del_mismo_usuario(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // Sesión "vieja" ya en la base, como si viniera de otro dispositivo.
        DB::table('sessions')->insert([
            'id' => 'session-vieja-de-otro-dispositivo',
            'user_id' => $user->id,
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-vieja-de-otro-dispositivo']);
    }

    public function test_session_check_reporta_si_hay_sesion_autenticada(): void
    {
        $this->getJson(route('session-check'))->assertJson(['authenticated' => false]);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('session-check'))->assertJson(['authenticated' => true]);
    }
}
