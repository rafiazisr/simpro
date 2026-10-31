<x-layouts.app title="Dokumentasi" :crumbs="['Dokumentasi']">
    <div class="page-head">
        <div>
            <h1 class="t-24">Dokumentasi</h1>
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="card empty" style="min-height: 260px; justify-content: center">
            <h3 class="t-16">Belum ada project</h3>
            <p>Dokumentasi tersedia setelah Anda menjadi anggota project.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 16px">
            @foreach ($projects as $p)
                <a class="card" href="{{ route('projects.docs.index', $p) }}" style="padding: 18px; display: flex; flex-direction: column; gap: 10px; text-decoration: none; color: inherit">
                    <div class="row between">
                        <span class="row" style="gap: 8px"><span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $p->color }}"></span><span class="code">{{ $p->code }}</span></span>
                        <x-icon name="book" style="color: var(--primary)" />
                    </div>
                    <span class="t-16">{{ $p->name }}</span>
                    <span class="t-12 muted">{{ $p->doc_modules_count }} modul · {{ (int) $p->features_count }} fitur terdokumentasi</span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
