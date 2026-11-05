<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setelah login, pengguna tidak boleh dibawa ke endpoint latar belakang
 * (mis. stream SSE notifikasi) yang tercatat saat session habis.
 */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function login(User $user)
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_stream_notifikasi_untuk_tamu_membalas_401_tanpa_mencatat_tujuan(): void
    {
        $this->get('/notifications/stream', ['Accept' => 'text/event-stream'])
            ->assertUnauthorized();

        $this->assertNull(session('url.intended'));
    }

    public function test_setelah_session_habis_di_stream_login_tetap_ke_dashboard(): void
    {
        $user = User::factory()->create();
        $this->get('/notifications/stream', ['Accept' => 'text/event-stream']);

        $this->login($user)->assertRedirect(route('dashboard'));
    }

    public function test_tujuan_berupa_endpoint_data_diabaikan(): void
    {
        $user = User::factory()->create();

        foreach (['/notifications/stream?since=x', '/dashboard/data', '/gantt/data'] as $url) {
            $this->post('/logout');
            $this->withSession(['url.intended' => url($url)]);
            $this->login($user)->assertRedirect(route('dashboard'));
        }
    }

    public function test_tujuan_berupa_halaman_biasa_tetap_dipakai(): void
    {
        $user = User::factory()->create();
        $this->get('/notifications')->assertRedirect('/login');

        $this->login($user)->assertRedirect(url('/notifications'));
    }

    public function test_stream_dibuka_langsung_di_browser_diarahkan_ke_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/notifications/stream?since=x', ['Accept' => 'text/html,application/xhtml+xml'])
            ->assertRedirect(route('dashboard'));
    }
}
