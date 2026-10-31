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
        <div class="card">
            @foreach ($projects as $p)
                <a class="list-row" href="{{ route('projects.docs.index', $p) }}">
                    <span class="gantt-sw" style="background: {{ $p->color }}"></span>
                    <span class="code" style="width: 56px">{{ $p->code }}</span>
                    <span class="fw-500 grow" style="color: var(--text)">{{ $p->name }}</span>
                    <span class="t-12 muted">{{ $p->doc_modules_count }} modul, {{ (int) $p->features_count }} fitur</span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
