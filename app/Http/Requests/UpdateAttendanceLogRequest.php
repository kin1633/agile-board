<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:full_leave,half_am,half_pm,early_leave,late_arrival'],
            // 早退・遅刻の場合は時刻必須、それ以外はNULL許容
            'time' => ['nullable', 'date_format:H:i', 'required_if:type,early_leave', 'required_if:type,late_arrival'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
