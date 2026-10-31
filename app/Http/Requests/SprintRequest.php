<?php

namespace App\Http\Requests;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Support\Format;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class SprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSprints', $this->project());
    }

    public function project(): Project
    {
        return $this->route('project');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', new Enum(SprintStatus::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $p = $this->project();

                if ($this->date('start_date')->lt($p->start_date)) {
                    $v->errors()->add('start_date', 'Sebelum tanggal mulai project ('.Format::date($p->start_date).').');
                }
                if ($this->date('end_date')->gt($p->end_date)) {
                    $v->errors()->add('end_date', 'Melewati tanggal selesai project ('.Format::date($p->end_date).').');
                }
            },
        ];
    }

    public function messages(): array
    {
        return ['end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'];
    }

    public function attributes(): array
    {
        return ['name' => 'nama sprint', 'goal' => 'goal', 'start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai'];
    }
}
