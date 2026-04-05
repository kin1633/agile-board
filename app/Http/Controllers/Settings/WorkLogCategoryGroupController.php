<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLogCategoryGroupRequest;
use App\Http\Requests\UpdateWorkLogCategoryGroupRequest;
use App\Models\WorkLogCategoryGroup;
use Illuminate\Http\RedirectResponse;

class WorkLogCategoryGroupController extends Controller
{
    /**
     * 新しいグループを作成する。
     */
    public function store(StoreWorkLogCategoryGroupRequest $request): RedirectResponse
    {
        WorkLogCategoryGroup::create($request->validated());

        return back();
    }

    /**
     * グループ名・並び順を更新する。
     */
    public function update(UpdateWorkLogCategoryGroupRequest $request, WorkLogCategoryGroup $workLogCategoryGroup): RedirectResponse
    {
        $workLogCategoryGroup->update($request->validated());

        return back();
    }

    /**
     * グループを削除する。
     * 紐付く種別の FK は nullOnDelete 制約により自動的に NULL になる。
     */
    public function destroy(WorkLogCategoryGroup $workLogCategoryGroup): RedirectResponse
    {
        $workLogCategoryGroup->delete();

        return back();
    }
}
