<?php

namespace Tests\Feature;

use App\Enums\StatusCategory;
use App\Models\Backlog;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacklogTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $prog;

    private User $qa;

    private Project $project;

    private Sprint $sprint;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->pm = User::factory()->create();
        $this->prog = User::factory()->create();
        $this->qa = User::factory()->create();
        $this->project = $this->makeProject(['pm' => $this->pm, 'programmer' => $this->prog, 'qa' => $this->qa], ['code' => 'PHR']);
        $this->sprint = $this->project->sprints()->create(['name' => 'Sprint 1', 'start_date' => '2026-03-01', 'end_date' => '2026-03-21', 'status' => 'active']);
    }

    private function data(array $override = []): array
    {
        return $override + [
            'title' => 'Integrasi login SSO',
            'detail' => '<div>Ganti login</div>',
            'sprint_id' => $this->sprint->id,
            'status_name' => 'To Do',
            'priority' => 'high',
            'programmer_id' => $this->prog->id,
            'qa_id' => $this->qa->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-10',
        ];
    }

    private function createBacklog(array $override = []): Backlog
    {
        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', $this->data($override))->assertSessionHasNoErrors();

        return Backlog::latest('id')->firstOrFail();
    }

    public function test_kode_backlog_otomatis_berurutan(): void
    {
        $a = $this->createBacklog();
        $b = $this->createBacklog(['title' => 'Kedua']);

        $this->assertSame('PHR-1', $a->code);
        $this->assertSame('PHR-2', $b->code);

        // Nomor tidak dipakai ulang walau backlog dihapus.
        $b->delete();
        $this->assertSame('PHR-3', $this->createBacklog(['title' => 'Ketiga'])->code);
    }

    public function test_status_baru_dibuat_otomatis_tanpa_duplikat(): void
    {
        $this->createBacklog(['status_name' => 'Blocked vendor']);
        $this->createBacklog(['status_name' => 'blocked VENDOR']);
        $this->createBacklog(['status_name' => 'in progress']);

        $custom = Status::where('project_id', $this->project->id)->get();
        $this->assertCount(1, $custom);
        $this->assertSame('Blocked vendor', $custom->first()->name);
        $this->assertSame(StatusCategory::Todo, $custom->first()->category);

        $this->assertSame('In Progress', Backlog::latest('id')->first()->status->name);
    }

    public function test_programmer_dan_qa_harus_sesuai_role_dan_wajib_di_sprint(): void
    {
        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', $this->data(['programmer_id' => $this->qa->id]))
            ->assertSessionHasErrors('programmer_id');

        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', $this->data(['qa_id' => '']))
            ->assertSessionHasErrors('qa_id');

        // Product Backlog boleh tanpa assignee.
        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', $this->data(['sprint_id' => '', 'programmer_id' => '', 'qa_id' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_tanggal_backlog_harus_di_dalam_sprint(): void
    {
        $this->actingAs($this->pm)->post('/projects/PHR/backlogs', $this->data(['end_date' => '2026-03-30']))
            ->assertSessionHasErrors('end_date');
    }

    public function test_programmer_tidak_bisa_membuat_backlog(): void
    {
        $this->actingAs($this->prog)->post('/projects/PHR/backlogs', $this->data())->assertForbidden();
    }

    public function test_alur_programmer_ke_qa_sampai_done(): void
    {
        $b = $this->createBacklog(['status_name' => 'In Progress']);

        $this->actingAs($this->qa)->patch("/backlogs/{$b->code}/status", ['action' => 'pass'])->assertForbidden();

        $this->actingAs($this->prog)->patch("/backlogs/{$b->code}/status", ['action' => 'send_to_qa'])->assertRedirect();
        $this->assertSame('Ready for QA', $b->fresh()->status->name);

        $this->actingAs($this->qa)->patch("/backlogs/{$b->code}/status", ['action' => 'reopen'])->assertRedirect();
        $this->assertSame('Reopen', $b->fresh()->status->name);

        $this->actingAs($this->prog)->patch("/backlogs/{$b->code}/status", ['action' => 'send_to_qa'])->assertRedirect();
        $this->actingAs($this->qa)->patch("/backlogs/{$b->code}/status", ['action' => 'pass'])->assertRedirect();

        $fresh = $b->fresh();
        $this->assertSame('Done', $fresh->status->name);
        $this->assertNotNull($fresh->completed_at);

        // Riwayat status tercatat: dibuat + 4 perubahan status.
        $this->assertSame(4, $fresh->activities()->where('field', 'status')->count());
        $this->assertSame(1, $fresh->activities()->where('field', 'created')->count());
    }

    public function test_assignee_lain_tidak_bisa_mengubah_status(): void
    {
        $other = User::factory()->create();
        $this->project->members()->attach($other->id, ['project_role' => 'programmer']);
        $b = $this->createBacklog();

        $this->actingAs($other)->patch("/backlogs/{$b->code}/status", ['action' => 'send_to_qa'])->assertForbidden();
        // Programmer tidak boleh memakai status bebas.
        $this->actingAs($this->prog)->patch("/backlogs/{$b->code}/status", ['status_name' => 'Done'])->assertForbidden();
    }

    public function test_pm_mengubah_backlog_tercatat_di_riwayat(): void
    {
        $b = $this->createBacklog();

        $this->actingAs($this->pm)->put("/backlogs/{$b->code}", $this->data(['priority' => 'critical', 'title' => 'Judul baru']))
            ->assertRedirect('/projects/PHR/backlogs');

        $b->refresh();
        $this->assertSame('critical', $b->priority->value);
        $this->assertDatabaseHas('backlog_activities', ['backlog_id' => $b->id, 'field' => 'priority', 'old_value' => 'High', 'new_value' => 'Critical']);
        $this->assertDatabaseHas('backlog_activities', ['backlog_id' => $b->id, 'field' => 'title', 'new_value' => 'Judul baru']);
    }

    public function test_detail_backlog_dan_pencarian(): void
    {
        $b = $this->createBacklog();

        $this->actingAs($this->prog)->get("/backlogs/{$b->code}")->assertOk()->assertSee('Selesai, kirim ke QA');
        $this->actingAs($this->qa)->get("/backlogs/{$b->code}")->assertOk()->assertDontSee('Selesai, kirim ke QA');

        $this->actingAs($this->prog)->get('/cari?q=PHR-1')->assertRedirect("/backlogs/{$b->code}");
        $this->actingAs($this->prog)->get('/cari?q=login')->assertOk()->assertSee('Integrasi login SSO');

        // Bukan anggota project tidak bisa melihat.
        $this->actingAs(User::factory()->create())->get("/backlogs/{$b->code}")->assertForbidden();
    }

    public function test_tab_backlog_tabel_board_dan_filter_dengan_data(): void
    {
        $this->createBacklog();
        $this->createBacklog(['title' => 'Di product backlog', 'sprint_id' => '', 'programmer_id' => '', 'qa_id' => '', 'status_name' => 'Blocked vendor']);

        $this->actingAs($this->pm)->get('/projects/PHR/backlogs')->assertOk()
            ->assertSee('Integrasi login SSO')->assertSee('Product Backlog')->assertSee('Blocked vendor');
        $this->actingAs($this->pm)->get('/projects/PHR/backlogs?view=board')->assertOk()->assertSee('Integrasi login SSO');
        $this->actingAs($this->pm)->get('/projects/PHR/backlogs?view=board&sprint=none')->assertOk()->assertSee('Di product backlog');
        $this->actingAs($this->pm)->get('/projects/PHR/backlogs?priority=high&q=SSO')->assertOk()->assertSee('Integrasi login SSO');
        $this->actingAs($this->pm)->get('/projects/PHR')->assertOk()->assertSee('Blocked vendor');
        $this->actingAs($this->pm)->get('/projects/PHR/sprints')->assertOk()->assertSee('Sprint 1');
        $this->actingAs($this->pm)->get('/backlogs/PHR-1')->assertOk()->assertSee('Simpan status');
    }

    public function test_drawer_edit_dan_hapus(): void
    {
        $b = $this->createBacklog();

        $this->actingAs($this->pm)->get("/backlogs/{$b->code}/edit")->assertOk()->assertSee('Ubah '.$b->code);
        $this->actingAs($this->pm)->delete("/backlogs/{$b->code}")->assertRedirect('/projects/PHR/backlogs');
        $this->assertSoftDeleted($b);
    }
}
