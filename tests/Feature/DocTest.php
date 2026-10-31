<?php

namespace Tests\Feature;

use App\Models\Backlog;
use App\Models\DocFeature;
use App\Models\DocModule;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $prog;

    private User $qa;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->pm = User::factory()->create();
        $this->prog = User::factory()->create();
        $this->qa = User::factory()->create();
        $this->project = $this->makeProject(['pm' => $this->pm, 'programmer' => $this->prog, 'qa' => $this->qa], ['code' => 'PHR']);
    }

    private function feature(string $title = 'Login SSO'): DocFeature
    {
        $module = $this->project->docModules()->create(['name' => 'Autentikasi', 'sort_order' => 1]);

        return $module->features()->create(['title' => $title, 'content' => '<div>isi</div>', 'sort_order' => 1]);
    }

    public function test_halaman_dokumentasi_kosong_dan_daftar(): void
    {
        $this->actingAs($this->qa)->get('/dokumentasi')->assertOk()->assertSee($this->project->name);
        $this->actingAs($this->qa)->get('/projects/PHR/docs')->assertOk()->assertSee('Belum ada dokumentasi');
    }

    public function test_programmer_membuat_modul_dan_fitur(): void
    {
        $this->actingAs($this->prog)->post('/projects/PHR/docs/modules', ['name' => 'Autentikasi'])->assertRedirect();
        $module = DocModule::firstOrFail();

        $this->actingAs($this->prog)->get("/projects/PHR/docs/modules/{$module->id}/features/create")->assertOk()->assertSee('Fitur baru di Autentikasi');

        $this->actingAs($this->prog)->post("/projects/PHR/docs/modules/{$module->id}/features", [
            'title' => 'Login SSO',
            'content' => '<h2>Alur</h2><div>Klik <strong>Masuk</strong><script>x()</script></div><figure><img src="/storage/docs/a.png" onerror="x"></figure><img src="https://evil.test/a.png">',
        ])->assertRedirect();

        $f = DocFeature::firstOrFail();
        $this->assertStringContainsString('<strong>Masuk</strong>', $f->content);
        $this->assertStringContainsString('src="/storage/docs/a.png"', $f->content);
        $this->assertStringNotContainsString('script', $f->content);
        $this->assertStringNotContainsString('onerror', $f->content);
        $this->assertStringNotContainsString('evil.test', $f->content);
        $this->assertSame($this->prog->id, $f->updated_by);

        // Tab dokumentasi langsung membuka fitur pertama.
        $this->actingAs($this->qa)->get('/projects/PHR/docs')->assertRedirect("/projects/PHR/docs/{$f->id}");
        $this->actingAs($this->qa)->get("/projects/PHR/docs/{$f->id}")->assertOk()->assertSee('Login SSO');
    }

    public function test_qa_hanya_bisa_membaca_dan_programmer_tidak_bisa_menghapus(): void
    {
        $f = $this->feature();

        $this->actingAs($this->qa)->post('/projects/PHR/docs/modules', ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->qa)->get("/projects/PHR/docs/{$f->id}/edit")->assertForbidden();
        $this->actingAs($this->prog)->delete("/projects/PHR/docs/{$f->id}")->assertForbidden();

        $this->actingAs($this->pm)->delete("/projects/PHR/docs/{$f->id}")->assertRedirect('/projects/PHR/docs');
        $this->assertModelMissing($f);
    }

    public function test_fitur_dari_project_lain_tidak_bisa_diakses_lewat_url(): void
    {
        $f = $this->feature();
        $other = $this->makeProject(['pm' => $this->pm], ['code' => 'OTH']);

        $this->actingAs($this->pm)->get("/projects/OTH/docs/{$f->id}")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/projects/PHR/docs/{$f->id}")->assertForbidden();
    }

    public function test_hubungkan_dan_lepas_backlog(): void
    {
        $f = $this->feature();
        $b = Backlog::create(['project_id' => $this->project->id, 'number' => 1, 'code' => 'PHR-1', 'title' => 'Integrasi SSO', 'status_id' => Status::where('name', 'Testing')->first()->id, 'priority' => 'high']);
        $other = $this->makeProject(['pm' => $this->pm], ['code' => 'OTH']);
        $foreign = Backlog::create(['project_id' => $other->id, 'number' => 1, 'code' => 'OTH-1', 'title' => 'Asing', 'status_id' => Status::first()->id, 'priority' => 'low']);

        $this->actingAs($this->pm)->get("/projects/PHR/docs/{$f->id}?mode=link")->assertOk()->assertSee('PHR-1');

        $this->actingAs($this->pm)->post("/projects/PHR/docs/{$f->id}/backlogs", ['backlog_ids' => [$foreign->id]])->assertSessionHasErrors();
        $this->actingAs($this->pm)->post("/projects/PHR/docs/{$f->id}/backlogs", ['backlog_ids' => [$b->id]])->assertRedirect();

        $this->actingAs($this->qa)->get("/projects/PHR/docs/{$f->id}")->assertSee('PHR-1')->assertSee('Testing');
        // Detail backlog menampilkan dokumentasi yang terhubung.
        $this->actingAs($this->qa)->get('/backlogs/PHR-1')->assertSee('Login SSO');

        $this->actingAs($this->pm)->delete("/projects/PHR/docs/{$f->id}/backlogs/PHR-1")->assertRedirect();
        $this->assertSame(0, $f->backlogs()->count());
    }

    public function test_urutan_modul_dan_fitur(): void
    {
        $a = $this->project->docModules()->create(['name' => 'A', 'sort_order' => 1]);
        $b = $this->project->docModules()->create(['name' => 'B', 'sort_order' => 2]);

        $this->actingAs($this->pm)->patch("/projects/PHR/docs/modules/{$b->id}/move", ['dir' => 'up'])->assertRedirect();

        $this->assertSame(['B', 'A'], $this->project->docModules()->pluck('name')->all());
    }

    private function png(string $name = 'alur.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    public function test_upload_gambar(): void
    {
        Storage::fake('public');

        $res = $this->actingAs($this->prog)->post('/dokumentasi/upload-image', [
            'project' => 'PHR',
            'file' => $this->png(),
        ])->assertOk();

        $url = $res->json('url');
        $this->assertStringStartsWith('/storage/docs/phr-', $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));

        $this->actingAs($this->prog)->post('/dokumentasi/upload-image', [
            'project' => 'PHR',
            'file' => UploadedFile::fake()->create('dok.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->qa)->post('/dokumentasi/upload-image', [
            'project' => 'PHR',
            'file' => $this->png('a.png'),
        ])->assertForbidden();
    }
}
