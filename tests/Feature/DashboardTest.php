<?php

namespace Tests\Feature;

use App\Models\Backlog;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $prog;

    private User $qa;

    private User $other;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->prog = User::factory()->create();
        $this->qa = User::factory()->create();
        $this->other = User::factory()->create();
        $this->project = $this->makeProject(['programmer' => [$this->prog, $this->other], 'qa' => $this->qa], ['code' => 'PHR', 'name' => 'Portal HR']);

        $s = fn (string $name) => Status::whereNull('project_id')->where('name', $name)->first()->id;
        $rows = [
            ['Mine todo', 'To Do', $this->prog],
            ['Mine progress', 'In Progress', $this->prog],
            ['Mine ready', 'Ready for QA', $this->prog],
            ['Mine done', 'Done', $this->prog],
            ['Other todo', 'To Do', $this->other],
        ];
        foreach ($rows as $i => [$title, $status, $user]) {
            Backlog::create([
                'project_id' => $this->project->id, 'number' => $i + 1, 'code' => 'PHR-'.($i + 1), 'title' => $title,
                'status_id' => $s($status), 'priority' => 'medium', 'programmer_id' => $user->id, 'qa_id' => $this->qa->id,
                'end_date' => $status === 'To Do' && $user === $this->prog ? today()->subDay() : null,
            ]);
        }
    }

    public function test_dashboard_admin_melihat_semua_backlog(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Dashboard')->assertSee('project aktif')->assertSee('terlambat');

        $data = $this->actingAs($admin)->getJson('/dashboard/data?project='.$this->project->id)->assertOk()->json();
        $this->assertSame(5, $data['total']);
        $this->assertSame(20, $data['done_pct']);
        $this->assertSame(3, $data['n_programmer'], 'To Do x2 + In Progress');
        $this->assertSame(1, $data['n_qa']);
        $this->assertStringContainsString('Other todo', $data['rows_html']);
        $this->assertStringContainsString('terlambat', $data['rows_html']);
    }

    public function test_dashboard_non_admin_hanya_backlog_miliknya(): void
    {
        $this->actingAs($this->prog)->get('/dashboard')->assertOk()->assertSee('Backlog Saya')->assertSee('tugas aktif');

        $data = $this->actingAs($this->prog)->getJson('/dashboard/data?project='.$this->project->id)->json();
        $this->assertSame(4, $data['total']);
        $this->assertStringContainsString('Mine todo', $data['rows_html']);
        $this->assertStringNotContainsString('Other todo', $data['rows_html']);

        $qa = $this->actingAs($this->qa)->getJson('/dashboard/data?project='.$this->project->id.'&tab=qa')->json();
        $this->assertStringContainsString('Mine ready', $qa['rows_html']);
        $this->assertStringContainsString('role-qa', $qa['rows_html']);
    }

    public function test_klik_slice_memfilter_list_berdasarkan_status(): void
    {
        $done = Status::whereNull('project_id')->where('name', 'Done')->first();

        $data = $this->actingAs($this->prog)->getJson("/dashboard/data?project={$this->project->id}&status={$done->id}")->json();

        $this->assertSame(['id' => $done->id, 'name' => 'Done'], $data['selected']);
        $this->assertStringContainsString('Mine done', $data['rows_html']);
        $this->assertStringNotContainsString('Mine todo', $data['rows_html']);
        $this->assertSame(['To Do', 'In Progress', 'Ready for QA', 'Done'], array_column($data['slices'], 'name'));
    }

    public function test_project_orang_lain_ditolak(): void
    {
        $foreign = $this->makeProject([], ['code' => 'OTH']);

        $this->actingAs($this->prog)->getJson('/dashboard/data?project='.$foreign->id)->assertForbidden();
    }

    public function test_user_tanpa_project_melihat_empty_state(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee('Anda belum menjadi anggota project mana pun');
    }
}
