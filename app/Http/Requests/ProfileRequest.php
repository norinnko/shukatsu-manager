<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:100'],
            'full_name_kana' => ['nullable', 'string', 'max:100'],
            'full_name_roman' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:255'],
            'github_url' => ['nullable', 'url:http,https', 'max:255'],
            'portfolio_url' => ['nullable', 'url:http,https', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
