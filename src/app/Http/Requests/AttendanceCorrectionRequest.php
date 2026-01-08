<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'clock_in_at'  => ['required', 'date_format:H:i'],
            'clock_out_at' => ['required', 'date_format:H:i'],

            'breaks'         => ['nullable', 'array', 'max:10'],
            'breaks.*.start' => ['nullable', 'date_format:H:i'],
            'breaks.*.end'   => ['nullable', 'date_format:H:i'],

            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages()
    {
        return [
            'clock_in_at.required'     => '出勤時刻を入力してください',
            'clock_in_at.date_format'  => '出勤時刻はHH:MM形式で入力してください',
            'clock_out_at.required'    => '退勤時刻を入力してください',
            'clock_out_at.date_format' => '退勤時刻はHH:MM形式で入力してください',

            'breaks.*.start.date_format' => '休憩開始時刻はHH:MM形式で入力してください',
            'breaks.*.end.date_format'   => '休憩終了時刻はHH:MM形式で入力してください',

            'reason.required' => '備考を記入してください',
            'reason.max'      => '備考は255文字以内で入力してください',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function (Validator $validator) {

            $clockIn  = $this->input('clock_in_at');
            $clockOut = $this->input('clock_out_at');

            if (!$clockIn || !$clockOut) return;
            if (!preg_match('/^\d{2}:\d{2}$/', $clockIn)
                || !preg_match('/^\d{2}:\d{2}$/', $clockOut)) {
                return;
            }

            $clockInMinutes  = $this->toMinutes($clockIn);
            $clockOutMinutes = $this->toMinutes($clockOut);

            if ($clockInMinutes > $clockOutMinutes) {
                $validator->errors()->add(
                    'clock_in_at',
                    '出勤時間が不適切な値です'
                );
                return;
            }

            $breaks = $this->input('breaks', []);

            foreach ($breaks as $index => $breakInput) {
                $breakStart = $breakInput['start'] ?? null;
                $breakEnd   = $breakInput['end'] ?? null;

                if (!$breakStart && !$breakEnd) continue;

                if (!$breakStart || !$breakEnd) {
                    $validator->errors()->add(
                        "breaks.$index.start",
                        '休憩時間が不適切な値です'
                    );
                    continue;
                }

                if (!preg_match('/^\d{2}:\d{2}$/', $breakStart)
                    || !preg_match('/^\d{2}:\d{2}$/', $breakEnd)) {
                    continue;
                }

                $breakStartMinutes = $this->toMinutes($breakStart);
                $breakEndMinutes   = $this->toMinutes($breakEnd);

                if ($breakStartMinutes > $breakEndMinutes) {
                    $validator->errors()->add(
                        "breaks.$index.start",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($breakStartMinutes > $clockOutMinutes) {
                    $validator->errors()->add(
                        "breaks.$index.start",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($breakEndMinutes > $clockOutMinutes) {
                    $validator->errors()->add(
                        "breaks.$index.end",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }
}
