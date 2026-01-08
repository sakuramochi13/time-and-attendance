<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'clock_in_at'  => ['required', 'date_format:H:i'],
            'clock_out_at' => ['required', 'date_format:H:i'],

            'breaks' => ['nullable', 'array'],

            'breaks.*.id' => ['nullable', 'integer'],

            'breaks.*.break_start_at' => ['nullable', 'date_format:H:i'],
            'breaks.*.break_end_at'   => ['nullable', 'date_format:H:i'],

            'breaks.*._delete'        => ['nullable', 'boolean'],

            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in_at.required'     => '出勤時刻を入力してください。',
            'clock_out_at.required'    => '退勤時刻を入力してください。',
            'clock_in_at.date_format'  => '出勤時間は HH:MM 形式で入力してください。',
            'clock_out_at.date_format' => '退勤時間は HH:MM 形式で入力してください。',

            'breaks.*.break_start_at.date_format' => '休憩開始は HH:MM 形式で入力してください。',
            'breaks.*.break_end_at.date_format'   => '休憩終了は HH:MM 形式で入力してください。',

            'reason.required' => '備考を記入してください',
            'reason.max'      => '備考は255文字以内で入力してください。',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function (Validator $validator) {

            $clockIn  = $this->input('clock_in_at');
            $clockOut = $this->input('clock_out_at');

            if (!$this->isTime($clockIn) || !$this->isTime($clockOut)) {
                return;
            }

            $clockInMin  = $this->toMinutes($clockIn);
            $clockOutMin = $this->toMinutes($clockOut);

            if ($clockInMin > $clockOutMin) {
                $validator->errors()->add(
                    'clock_in_at',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
            }

            $breaks = $this->input('breaks', []);
            if (!is_array($breaks)) return;

            foreach ($breaks as $index => $breakInput) {

                $isDelete = (bool)($breakInput['_delete'] ?? false);
                if ($isDelete) continue;

                $start = $breakInput['break_start_at'] ?? null;
                $end   = $breakInput['break_end_at'] ?? null;

                if (!$start && !$end) continue;

                if (!$start || !$end) {
                    $validator->errors()->add(
                        "breaks.$index.break_start_at",
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                if (!$this->isTime($start) || !$this->isTime($end)) {
                    continue;
                }

                $startMin = $this->toMinutes($start);
                $endMin   = $this->toMinutes($end);

                if ($startMin > $endMin) {
                    $validator->errors()->add(
                        "breaks.$index.break_start_at",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($startMin < $clockInMin || $startMin > $clockOutMin) {
                    $validator->errors()->add(
                        "breaks.$index.break_start_at",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($endMin > $clockOutMin) {
                    $validator->errors()->add(
                        "breaks.$index.break_end_at",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }

    private function isTime($value): bool
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1;
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }
}