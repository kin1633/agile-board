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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // 1人日の基準時間は1〜24の範囲で設定可能
            'hours_per_person_day' => ['required', 'numeric', 'min:1', 'max:24'],
        ]);

        Setting::set('hours_per_person_day', (string) $validated['hours_per_person_day']);

        return redirect()->route('settings.general');
    }
}
