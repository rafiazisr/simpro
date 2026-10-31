<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? $this->user()->can('update', $project)
            : $this->user()->can('create', Project::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        // Baris anggota kosong dari form dinamis diabaikan.
        $this->merge([
            'members' => collect($this->input('members', []))
                ->filter(fn ($m) => ! empty($m['user_id']))
                ->values()->all(),
        ]);
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $isCreate = ! $project instanceof Project;

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:65000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', new Enum(ProjectStatus::class)],
        ];

        if ($isCreate) {
            $rules += [
                'code' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,9}$/', Rule::unique('projects', 'code')],
                'members' => ['required', 'array', 'min:1'],
                'members.*.user_id' => ['required', 'distinct', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
                'members.*.role' => ['required', Rule::in(ProjectRoles::options()->keys()->all())],
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                $project = $this->route('project');

                if (! $project instanceof Project && ! collect($this->input('members'))->contains('role', 'pm')) {
                    $v->errors()->add('members', 'Minimal 1 Project Manager.');
                }

                // Rentang project harus tetap mencakup semua sprint yang sudah ada.
                if ($project instanceof Project && ! $v->errors()->hasAny(['start_date', 'end_date'])) {
                    $first = $project->sprints()->min('start_date');
                    $last = $project->sprints()->max('end_date');

                    if ($first && $this->date('start_date')->gt($first)) {
                        $v->errors()->add('start_date', 'Ada sprint yang dimulai sebelum tanggal ini.');
                    }
                    if ($last && $this->date('end_date')->lt($last)) {
                        $v->errors()->add('end_date', 'Ada sprint yang selesai setelah tanggal ini.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode 2–10 karakter, diawali huruf, hanya huruf kapital dan angka.',
            'code.unique' => 'Kode :input sudah dipakai.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'members.required' => 'Tambahkan minimal 1 anggota.',
            'members.*.user_id.distinct' => 'User yang sama dipilih lebih dari sekali.',
            'members.*.user_id.exists' => 'User tidak ditemukan atau nonaktif.',
            'members.*.role.required' => 'Role wajib dipilih.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode', 'name' => 'nama project', 'description' => 'deskripsi',
            'start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai', 'status' => 'status',
        ];
    }
}
