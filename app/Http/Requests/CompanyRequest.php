<?php

namespace App\Http\Requests;

use App\Enums\CompanyPhase;
use App\Enums\CompanyPriority;
use App\Enums\CompanyStage;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company|null $company */
        $company = $this->route('company');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('companies', 'name')->where('user_id', $this->user()->id)->ignore($company?->id),
            ],
            'industry' => ['nullable', 'string', 'max:100'],
            'phase' => ['required', Rule::enum(CompanyPhase::class)],
            'stage' => ['required', Rule::enum(CompanyStage::class)],
            'priority' => ['nullable', Rule::enum(CompanyPriority::class)],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'mypage_url' => ['nullable', 'url:http,https', 'max:1024'],
            'mypage_login_id' => ['nullable', 'string', 'max:255'],
            'recruit_url' => ['nullable', 'url:http,https', 'max:1024'],
            'test_type' => ['nullable', 'string', 'max:50'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'result_expected_on' => ['nullable', 'date_format:Y-m-d'],
            'memo' => ['nullable', 'string', 'max:10000'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function companyData(): array
    {
        $data = $this->safe()->except('archived');

        if ($this->has('archived')) {
            /** @var Company|null $company */
            $company = $this->route('company');
            $data['archived_at'] = $this->boolean('archived') ? ($company?->archived_at ?? now()) : null;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '企業名',
            'mypage_url' => 'マイページURL',
            'recruit_url' => '採用ページURL',
        ];
    }
}
