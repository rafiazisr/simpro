<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Token CSRF kedaluwarsa (419) tidak boleh menampilkan halaman "Page Expired";
 * pengguna diarahkan kembali dengan pesan yang jelas.
 */
class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        // Middleware CSRF Laravel otomatis nonaktif saat unit test.
        // Di sini dipaksa aktif agar alur 419 yang sebenarnya ikut teruji.
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_login_dengan_token_kedaluwarsa_kembali_ke_login_dengan_pesan(): void
    {
        $this->post('/login', ['_token' => 'token-lama', 'email' => 'a@simpro.local', 'password' => 'rahasia123'])
            ->assertRedirect('/login')
            ->assertSessionHas('session_expired', true)
            ->assertSessionHasInput('email', 'a@simpro.local')
            ->assertSessionMissing('_old_input.password');

        $this->get('/login')->assertOk()->assertSee('Halaman login sudah kedaluwarsa');
    }

    public function test_sudah_login_di_tab_lain_langsung_ke_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/login', ['_token' => 'token-lama', 'email' => 'x@simpro.local', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_form_lain_kembali_ke_halaman_sebelumnya_dengan_peringatan(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/projects/create')
            ->post('/projects', ['_token' => 'token-lama', 'name' => 'Project Baru'])
            ->assertRedirect('/projects/create')
            ->assertSessionHas('toast_warn')
            ->assertSessionHasInput('name', 'Project Baru');
    }

    public function test_halaman_login_tidak_disimpan_di_cache_browser(): void
    {
        $this->assertStringContainsString('no-store', $this->get('/login')->headers->get('Cache-Control'));
    }
}
