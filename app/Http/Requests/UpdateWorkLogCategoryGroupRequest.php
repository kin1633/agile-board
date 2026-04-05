<?php

namespace App\Http\Requests;

use App\Models\WorkLogCategoryGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkLogCategoryGroupRequest extends FormRequest
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
        /** @var WorkLogCategoryGroup $group */
        $group = $this->route('workLogCategoryGroup');

        return [
            // 自分自身は除外して重複チェック
            'name' => ['required', 'string', 'max:100', Rule::unique('work_log_category_groups', 'name')->ignore($group->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
