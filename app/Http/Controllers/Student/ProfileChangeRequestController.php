<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use App\Services\Profile\ProfileChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A student asking staff to correct their name / guardian name. Always about the caller themselves. */
class ProfileChangeRequestController extends Controller
{
    public function __construct(private readonly ProfileChangeService $changes) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $this->changes->submit($request->user(), $data['name'], $data['guardian_name'] ?? null, $data['reason']);

        return back()->with('success', __('profile_requests.submitted'));
    }

    public function destroy(Request $request, ProfileChangeRequest $changeRequest): RedirectResponse
    {
        $this->changes->cancel($changeRequest, $request->user());

        return back()->with('success', __('profile_requests.cancelled'));
    }
}
