<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\Backlog;
use App\Models\Project;
use App\Models\Sprint;
use App\Support\Format;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class BacklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $backlog = $this->route('backlog');

        return $backlog instanceof Backlog
            ? $this->user()->can('update', $backlog)
            : $this->user()->can('createBacklog', $this->project());
    }

    public function project(): Project
    {
        $backlog = $this->route('backlog');

        return $backlog instanceof Backlog ? $backlog->project : $this->route('project');
    }

    public function rules(): array
    {
        $project = $this->project();
        $memberIds = fn (string $role) => $project->members()->wherePivot('project_role', $role)->pluck('users.id')->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:65000'],
            'sprint_id' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $project->id)],
            'status_name' => ['required', 'string', 'max:50'],
            'priority' => ['required', new Enum(Priority::class)],
            // Programmer dan QA wajib saat backlog masuk sprint; boleh kosong di Product Backlog.
            'programmer_id' => ['nullable', 'required_with:sprint_id', Rule::in($memberIds('programmer'))],
            'qa_id' => ['nullable', 'required_with:sprint_id', Rule::in($memberIds('qa'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->hasAny(['sprint_id', 'start_date', 'end_date']) || ! $this->filled('sprint_id')) {
                    return;
                }

                // Tanggal backlog harus di dalam rentang sprint.
                $sprint = Sprint::find($this->input('sprint_id'));
                $range = Format::dateRange($sprint->start_date, $sprint->end_date);

                if ($this->filled('start_date') && $this->date('start_date')->lt($sprint->start_date)) {
                    $v->errors()->add('start_date', "Harus di dalam rentang {$sprint->name} ({$range}).");
                }
                if ($this->filled('end_date') && $this->date('end_date')->gt($sprint->end_date)) {
                    $v->errors()->add('end_date', "Harus di dalam rentang {$sprint->name} ({$range}).");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'programmer_id.required_with' => 'Programmer wajib dipilih untuk backlog di dalam sprint.',
            'qa_id.required_with' => 'QA wajib dipilih untuk backlog di dalam sprint.',
            'programmer_id.in' => 'Pilih anggota project dengan role Programmer.',
            'qa_id.in' => 'Pilih anggota project dengan role QA.',
            'status_name.required' => 'Status wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul', 'detail' => 'detail', 'sprint_id' => 'sprint', 'priority' => 'prioritas',
            'start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai',
        ];
    }
}
