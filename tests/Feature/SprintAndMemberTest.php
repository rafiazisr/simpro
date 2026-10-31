<?php

namespace Tests\Feature;

use App\Enums\SprintStatus;
use App\Models\Backlog;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintAndMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_pm_membuat_sprint_di_dalam_rentang_project(): void
    {
        $pm = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);

        $this->actingAs($pm)->post("/projects/{$project->code}/sprints", [
            'name' => 'Sprint 1', 'goal' => 'Fondasi', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'status' => 'active',
        ])->assertRedirect("/projects/{$project->code}/sprints");

        $this->assertSame(1, $project->sprints()->count());

        $this->actingAs($pm)->post("/projects/{$project->code}/sprints", [
            'name' => 'Sprint X', 'start_date' => '2026-12-20', 'end_date' => '2027-01-08', 'status' => 'planned',
        ])->assertSessionHasErrors('end_date');
    }

    public function test_programmer_tidak_bisa_mengelola_sprint(): void
    {
        $prog = User::factory()->create();
        $project = $this->makeProject(['programmer' => $prog]);

        $this->actingAs($prog)->get("/projects/{$project->code}/sprints/create")->assertForbidden();
    }

    public function test_hapus_sprint_memindahkan_backlog_ke_product_backlog(): void
    {
        $pm = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);
        $sprint = $project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'status' => 'planned']);
        $backlog = Backlog::create([
            'project_id' => $project->id, 'sprint_id' => $sprint->id, 'number' => 1, 'code' => $project->code.'-1',
            'title' => 'A', 'status_id' => Status::first()->id, 'priority' => 'medium',
        ]);

        $this->actingAs($pm)->delete("/projects/{$project->code}/sprints/{$sprint->id}")->assertRedirect();

        $this->assertNull($backlog->fresh()->sprint_id);
    }

    public function test_tandai_sprint_selesai(): void
    {
        $pm = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);
        $sprint = $project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'status' => 'active']);

        $this->actingAs($pm)->patch("/projects/{$project->code}/sprints/{$sprint->id}/done")->assertRedirect();

        $this->assertSame(SprintStatus::Done, $sprint->fresh()->status);
        $this->assertNotNull($sprint->fresh()->done_at);
    }

    public function test_kelola_anggota(): void
    {
        $pm = User::factory()->create();
        $budi = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);

        $this->actingAs($pm)->post("/projects/{$project->code}/members", ['user_id' => $budi->id, 'role' => 'programmer'])->assertRedirect();
        $this->assertSame('programmer', $project->roleOf($budi));

        $this->actingAs($pm)->patch("/projects/{$project->code}/members/{$budi->id}", ['role' => 'qa'])->assertRedirect();
        $this->assertSame('qa', $project->roleOf($budi));

        $this->actingAs($pm)->delete("/projects/{$project->code}/members/{$budi->id}")->assertRedirect();
        $this->assertNull($project->roleOf($budi));
    }

    public function test_pm_terakhir_tidak_bisa_dikeluarkan(): void
    {
        $pm = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm]);

        $this->actingAs($pm)->delete("/projects/{$project->code}/members/{$pm->id}")->assertSessionHasErrors('member');
        $this->assertSame('pm', $project->roleOf($pm));
    }

    public function test_anggota_dengan_backlog_aktif_tidak_bisa_dikeluarkan(): void
    {
        $pm = User::factory()->create();
        $budi = User::factory()->create();
        $project = $this->makeProject(['pm' => $pm, 'programmer' => $budi]);
        Backlog::create([
            'project_id' => $project->id, 'number' => 1, 'code' => $project->code.'-1', 'title' => 'A',
            'status_id' => Status::where('name', 'In Progress')->first()->id, 'priority' => 'medium', 'programmer_id' => $budi->id,
        ]);

        $this->actingAs($pm)->delete("/projects/{$project->code}/members/{$budi->id}")->assertSessionHasErrors('member');
    }
}
