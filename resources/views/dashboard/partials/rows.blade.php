@forelse ($backlogs as $b)
    @php
        $role = ! $isAdmin ? ($b->qa_id === $user->id && $b->programmer_id !== $user->id ? 'QA' : 'Programmer') : null;
        $person = $isAdmin ? ($b->status->category->isQaStage() ? $b->qa : $b->programmer) : null;
    @endphp
    <tr style="cursor: pointer" onclick="window.location='{{ route('backlogs.show', $b) }}'">
        <td class="code"><a href="{{ route('backlogs.show', $b) }}" style="color: inherit; text-decoration: none">{{ $b->code }}</a></td>
        <td class="title"><a href="{{ route('backlogs.show', $b) }}" style="color: inherit; text-decoration: none">{{ $b->title }}</a></td>
        <td data-label="{{ $isAdmin ? 'Assignee' : 'Peran Anda' }}">
            @if ($isAdmin)
                @if ($person)<span class="person"><x-avatar :user="$person" size="sm" />{{ $person->name }}</span>@else<span class="muted">Belum di-assign</span>@endif
            @else
                <span class="badge {{ $role === 'QA' ? 'role-qa' : 'role-prog' }}">{{ $role }}</span>
            @endif
        </td>
        <td data-label="Status"><span class="{{ $b->status->badgeClass() }}"><span class="dot"></span>{{ $b->status->name }}</span></td>
        <td data-label="Prioritas"><span class="{{ $b->priority->cssClass() }}"><i></i>{{ $b->priority->label() }}</span></td>
        <td data-label="Selesai" class="{{ $b->isLate() ? 'late' : '' }}">{{ \App\Support\Format::date($b->end_date) }}@if ($b->isLate()) · terlambat @endif</td>
    </tr>
@empty
    <tr><td colspan="6" class="muted" style="text-align: center; padding: 32px 12px">Tidak ada backlog di daftar ini.</td></tr>
@endforelse
