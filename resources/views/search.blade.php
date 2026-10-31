<x-layouts.app title="Pencarian" :crumbs="['Pencarian']">
    <div class="page-head">
        <div>
            <h1 class="t-24">Hasil pencarian</h1>
            <p class="sub">@if ($term) {{ $results->count() }} backlog cocok dengan "{{ $term }}"@else Ketik kode (mis. PHR-12) atau judul backlog di kotak pencarian.@endif</p>
        </div>
    </div>

    <form class="m-only" method="GET" action="{{ route('search') }}" role="search">
        <div class="input-icon">
            <x-icon name="search" />
            <input class="input" type="search" name="q" value="{{ $term }}" placeholder="Cari backlog (kode atau judul)…" aria-label="Cari backlog" @unless ($term) autofocus @endunless>
        </div>
    </form>

    @if ($term && $results->isEmpty())
        <div class="card empty" style="min-height: 240px; justify-content: center">
            <h3 class="t-16">Tidak ada backlog yang cocok</h3>
            <p>Pencarian hanya mencakup project tempat Anda menjadi anggota.</p>
        </div>
    @elseif ($results->isNotEmpty())
        <div class="card">
            <div class="table-wrap">
                <table class="table hover table-cards">
                    <thead><tr><th>Kode</th><th>Judul</th><th>Project</th><th>Status</th><th>Programmer</th><th>QA</th></tr></thead>
                    <tbody>
                    @foreach ($results as $b)
                        <tr style="cursor: pointer" onclick="window.location='{{ route('backlogs.show', $b) }}'">
                            <td class="code">{{ $b->code }}</td>
                            <td class="title"><a href="{{ route('backlogs.show', $b) }}" style="color: inherit; text-decoration: none">{{ $b->title }}</a></td>
                            <td data-label="Project">{{ $b->project->name }}</td>
                            <td data-label="Status"><span class="{{ $b->status->badgeClass() }}"><span class="dot"></span>{{ $b->status->name }}</span></td>
                            <td data-label="Programmer">{{ $b->programmer?->name ?? '—' }}</td>
                            <td data-label="QA">{{ $b->qa?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
