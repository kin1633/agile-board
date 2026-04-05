<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/general', [
            'hoursPerPersonDay' => (float) Setting::get('hours_per_person_day', '7'),
            'workStartTime' => Setting::get('work_start_time', '10:00'),
            'workEndTime' => Setting::get('work_end_time', '19:00'),
            'epicGithubStatusOrder' => json_decode(
                Setting::where('key', 'epic_github_status_order')->value('value') ?? '[]',
                true
            ),
            // リリースバッファ日数（due_date から開発完了目標日までの営業日数）
            'releaseBufferDays' => (int) Setting::get('release_buffer_days', '0'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // 1人日の基準時間は1〜24の範囲で設定可能
            'hours_per_person_day' => ['required', 'numeric', 'min:1', 'max:24'],
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
            'epic_github_status_order' => ['present', 'array'],
            'epic_github_status_order.*' => ['string'],
            // リリースバッファは0以上の整数日（上限30日）
            'release_buffer_days' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        Setting::set('hours_per_person_day', (string) $validated['hours_per_person_day']);
        Setting::set('work_start_time', $validated['work_start_time']);
        Setting::set('work_end_time', $validated['work_end_time']);
        Setting::set('release_buffer_days', (string) $validated['release_buffer_days']);
        Setting::where('key', 'epic_github_status_order')
            ->update(['value' => json_encode($validated['epic_github_status_order'])]);

        return redirect()->route('settings.general');
    }
}
