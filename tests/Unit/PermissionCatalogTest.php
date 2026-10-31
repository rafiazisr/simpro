<?php

namespace Tests\Unit;

use App\Enums\UserLevel;
use App\Support\PermissionCatalog;
use PHPUnit\Framework\TestCase;

class PermissionCatalogTest extends TestCase
{
    public function test_nama_permission_unik_dan_memakai_titik(): void
    {
        $names = PermissionCatalog::names();

        $this->assertSame($names, array_values(array_unique($names)));

        foreach ($names as $name) {
            // Gate::before mengandalkan titik untuk membedakan permission dari method Policy.
            $this->assertStringContainsString('.', $name);
        }
    }

    public function test_default_role_hanya_merujuk_role_bawaan(): void
    {
        $defaults = PermissionCatalog::defaultsByRole();

        $this->assertSame(array_keys(PermissionCatalog::SYSTEM_ROLES), array_keys($defaults));
        $this->assertSame([], $defaults['super_admin'], 'Super Admin mendapat akses lewat Gate::before');
    }

    public function test_default_sesuai_keputusan_prd(): void
    {
        $defaults = PermissionCatalog::defaultsByRole();

        $this->assertContains('user.manage', $defaults['admin']);
        $this->assertNotContains('user.manage', $defaults['pm']);
        $this->assertContains('documentation.create-update', $defaults['programmer']);
        $this->assertNotContains('documentation.create-update', $defaults['qa']);
        $this->assertNotContains('documentation.delete', $defaults['programmer']);
        $this->assertContains('backlog.update-status', $defaults['qa']);
    }

    public function test_level_yang_bisa_dipilih_di_form_tidak_termasuk_super_admin(): void
    {
        $this->assertNotContains(UserLevel::SuperAdmin, UserLevel::assignable());
        $this->assertSame('super_admin', UserLevel::SuperAdmin->roleName());
    }
}
