<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HtmlSanitizer;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_header_keamanan_terpasang(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_pesan_dialog_konfirmasi_tidak_dirender_sebagai_html(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => '<img src=x onerror=alert(1)>']);

        $this->actingAs($admin)->get('/users')->assertOk()
            ->assertSee('x-text="message"', false)
            ->assertDontSee('x-html="message"', false);
    }

    public function test_sanitizer_menolak_link_ke_situs_lain_tanpa_skema(): void
    {
        foreach (['//evil.com', '/\\evil.com', "/\t/evil.com", ' javascript:alert(1)'] as $href) {
            $this->assertStringNotContainsString('href', HtmlSanitizer::clean('<a href="'.$href.'">x</a>'), $href);
        }

        $this->assertStringContainsString('href="/projects/PHR"', HtmlSanitizer::clean('<a href="/projects/PHR">x</a>'));
    }

    public function test_seeder_menolak_password_bawaan_di_production(): void
    {
        $this->app['env'] = 'production';
        config(['settings.super_admin.password' => 'ganti-password-ini']);

        $this->expectException(RuntimeException::class);
        $this->app->make(SuperAdminSeeder::class)->run();
    }

    public function test_seeder_jalan_di_production_dengan_password_kuat(): void
    {
        $this->app['env'] = 'production';
        config(['settings.super_admin.email' => 'sa@test.local', 'settings.super_admin.password' => 'Password-Kuat-2026']);

        $this->app->make(SuperAdminSeeder::class)->run();

        $this->assertTrue(User::where('email', 'sa@test.local')->exists());
    }
}
