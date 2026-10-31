<?php

namespace App\Http\Requests;

use App\Enums\UserLevel;
use App\Models\User;
use App\Support\ProjectRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $isCreate = ! $target instanceof User;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($target?->id),
            ],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', Password::min(8)],
            'user_level' => ['required', new Enum(UserLevel::class), Rule::in(array_map(fn ($l) => $l->value, UserLevel::assignable()))],
            'is_active' => ['boolean'],
            'projects' => ['array'],
            'projects.*.project_id' => ['required', 'integer', 'distinct', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'projects.*.role' => ['required', Rule::in(ProjectRoles::options()->keys()->all())],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah digunakan user lain.',
            'user_level.in' => 'Super Admin hanya bisa ditetapkan lewat seeder.',
            'projects.*.project_id.required' => 'Pilih project pada setiap baris penugasan.',
            'projects.*.project_id.distinct' => 'Project yang sama dipilih lebih dari sekali.',
            'projects.*.role.in' => 'Role project tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'email',
            'password' => 'password',
            'user_level' => 'user level',
        ];
    }

    public function syncsProjects(): bool
    {
        return $this->boolean('projects_sync');
    }

    public function assignments(): array
    {
        return collect($this->validated('projects', []))
            ->mapWithKeys(fn ($row) => [(int) $row['project_id'] => $row['role']])
            ->all();
    }

    public function payload(): array
    {
        $data = $this->safe()->only(['name', 'email', 'user_level']);
        $data['is_active'] = $this->boolean('is_active');

        if ($this->filled('password')) {
            $data['password'] = $this->input('password');
        }

        return $data;
    }
}
