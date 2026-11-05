<?php

namespace App\Http\Requests;

use App\Enums\EsCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EsAnswerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('user_id', $this->user()->id)],
            'category' => ['required', Rule::enum(EsCategory::class)],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:10000'],
            'char_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'question' => '設問',
            'answer' => '回答',
            'char_limit' => '文字数制限',
        ];
    }
}
