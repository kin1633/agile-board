<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(): Response
    {
        $members = Member::orderBy('github_login')
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'github_login' => $m->github_login,
                'display_name' => $m->display_name,
                'daily_hours' => $m->daily_hours,
            ]);

        return Inertia::render('settings/members', compact('members'));
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'daily_hours' => ['required', 'numeric', 'min:0', 'max:24'],
        ]);

        $member->update($validated);

        return redirect()->route('settings.members');
    }
}
