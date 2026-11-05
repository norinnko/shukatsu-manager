<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school' => ['required', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:50'],
            'entered_on' => ['required', 'date_format:Y-m'],
            'graduated_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:entered_on'],
            'graduation_status' => ['required', Rule::in(['graduated', 'expected'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'school' => '学校名',
            'entered_on' => '入学年月',
            'graduated_on' => '卒業年月',
        ];
    }
}
