<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Events are edited as separate date and time fields (friendlier on phones)
 * and combined into starts_at / ends_at here.
 */
class EventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('user_id', $this->user()->id)],
            'type' => ['required', Rule::enum(EventType::class)],
            'title' => ['required', 'string', 'max:255'],
            'all_day' => ['required', 'boolean'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', Rule::requiredIf(fn () => ! $this->boolean('all_day')), 'date_format:H:i'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'online_url' => ['nullable', 'url:http,https', 'max:1024'],
            'memo' => ['nullable', 'string', 'max:5000'],
            'sync_google' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$start, $end] = $this->times();

                if ($end !== null && $end->lessThan($start)) {
                    $validator->errors()->add('end_time', '終了は開始より後にしてください。');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        [$start, $end] = $this->times();

        return [
            ...$this->safe()->only(['company_id', 'type', 'title', 'location', 'online_url', 'memo']),
            'all_day' => $this->boolean('all_day'),
            'starts_at' => $start,
            'ends_at' => $end,
            'sync_google' => $this->boolean('sync_google'),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable|null}
     */
    private function times(): array
    {
        $allDay = $this->boolean('all_day');
        $startDate = (string) $this->input('start_date');
        $endDate = $this->input('end_date') ?: null;

        if ($allDay) {
            $start = CarbonImmutable::parse($startDate)->startOfDay();
            $end = $endDate && $endDate !== $startDate ? CarbonImmutable::parse($endDate)->startOfDay() : null;

            return [$start, $end];
        }

        $start = CarbonImmutable::parse("{$startDate} {$this->input('start_time')}");
        $endTime = $this->input('end_time');
        $end = $endTime ? CarbonImmutable::parse(($endDate ?? $startDate)." {$endTime}") : null;

        return [$start, $end];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'start_date' => '日付',
            'start_time' => '開始時刻',
            'end_date' => '終了日',
            'end_time' => '終了時刻',
            'online_url' => '参加URL',
        ];
    }
}
