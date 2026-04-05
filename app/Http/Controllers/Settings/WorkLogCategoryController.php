<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLogCategoryRequest;
use App\Http\Requests\UpdateWorkLogCategoryRequest;
use App\Models\WorkLog;
use App\Models\WorkLogCategory;
use App\Models\WorkLogCategoryGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkLogCategoryController extends Controller
{
    /**
     * カテゴリ設定一覧ページを表示する。
     */
    public function index(): Response
    {
        $categories = WorkLogCategory::orderBy('sort_order')->get();
        $groups = WorkLogCategoryGroup::orderBy('sort_order')->get();

        return Inertia::render('settings/work-log-categories', [
            'categories' => $categories,
            'groups' => $groups,
        ]);
    }

    /**
     * 新しいカテゴリを作成する。
     * value はユーザーが指定せず自動生成する（重複・予測を避けるため）。
     */
    public function store(StoreWorkLogCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['value'] = 'custom_'.Str::random(8);

        WorkLogCategory::create($validated);

        return back();
    }

    /**
     * カテゴリを更新する。
     */
    public function update(UpdateWorkLogCategoryRequest $request, WorkLogCategory $workLogCategory): RedirectResponse
    {
        $workLogCategory->update($request->validated());

        return back();
    }

    /**
     * カテゴリを削除する。
     * デフォルト種別（開発作業）は削除不可。
     * 削除前に参照している work_logs の category を null に更新する。
     */
    public function destroy(WorkLogCategory $workLogCategory): RedirectResponse
    {
        if ($workLogCategory->is_default) {
            abort(403, 'デフォルト種別は削除できません。');
        }

        // 削除されるカテゴリを参照している実績のカテゴリをnullにリセット
        WorkLog::where('category', $workLogCategory->value)->update(['category' => null]);

        $workLogCategory->delete();

        return back();
    }
}
