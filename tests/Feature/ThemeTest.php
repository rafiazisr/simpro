<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_halaman_login_memakai_tema_teal_tanpa_tombol_tema(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<meta name="theme-color" content="#1C1F23">', false)
            ->assertSee('auth-bg', false)
            ->assertSee('rel="icon"', false)
            ->assertDontSee('theme-toggle', false)
            ->assertDontSee("localStorage.getItem('theme')", false);
    }

    public function test_halaman_aplikasi_tidak_punya_tombol_mode_gelap(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertSee('<meta name="theme-color" content="#1C1F23">', false)
            ->assertDontSee('themeToggle', false)
            ->assertDontSee('Ganti ke mode gelap', false);
    }
}
