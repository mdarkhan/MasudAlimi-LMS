<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use App\Services\Profile\ProfileChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Staff decide on students' name / guardian-name correction requests. Gated by perm:students.update on the routes. */
class ProfileRequestController extends Controller
{
    public function __construct(private readonly ProfileChangeService $changes) {}

    public function index(): View
    {
        return view('admin.profile-requests.index', [
            'pending' => ProfileChangeRequest::query()->pending()->with('student:id,name,roll,guardian_name')->oldest()->get(),
            'recent' => ProfileChangeRequest::query()->whereIn('status', [ProfileChangeRequest::APPROVED, ProfileChangeRequest::REJECTED])
                ->with(['student:id,name,roll', 'reviewer:id,name'])->latest('reviewed_at')->limit(15)->get(),
        ]);
    }

    public function approve(Request $request, ProfileChangeRequest $changeRequest): RedirectResponse
    {
        $ok = $this->changes->approve($changeRequest, $request->user(), $request->input('note'));

        return back()->with($ok ? 'success' : 'error', $ok ? __('profile_requests.approved_flash') : __('profile_requests.already_decided'));
    }

    public function reject(Request $request, ProfileChangeRequest $changeRequest): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $ok = $this->changes->reject($changeRequest, $request->user(), $request->input('note'));

        return back()->with($ok ? 'success' : 'error', $ok ? __('profile_requests.rejected_flash') : __('profile_requests.already_decided'));
    }
}
