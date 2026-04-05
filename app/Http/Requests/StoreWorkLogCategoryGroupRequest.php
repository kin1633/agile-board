<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkLogCategoryGroupRequest extends FormRequest
{
    /**
     * 認証済みユーザーであれば誰でも実行可能。
     */
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
            'name' => ['required', 'string', 'max:100', 'unique:work_log_category_groups,name'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_billable' => ['nullable', 'boolean'],
        ];
    }
}
