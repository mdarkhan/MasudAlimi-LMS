<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AmolNoteRequest;
use App\Models\User;
use App\Services\Amol\AmolService;
use App\Services\Amol\AmolStatsService;
use App\Services\Audit\AuditLogger;
use App\Support\AmolDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The ustaz side of the daily amol tracker — read-only checklists plus a per-day
 * comment. Reachable by any admin holding `amol.view` (route middleware).
 */
class AmolController extends Controller
{
    public function __construct(private readonly AmolService $amol, private readonly AmolStatsService $stats) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $students = User::query()->students()
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('roll', 'like', "%{$search}%")
            ))
            ->orderByRoll()
            ->paginate(20)
            ->withQueryString();

        return view('admin.amol.index', ['students' => $students, 'search' => $search]);
    }

    public function show(Request $request, User $student): View
    {
        abort_unless($student->isStudent(), 404);

        $date = AmolDate::resolve($request->query('date'));

        return view('admin.amol.show', [
            'student' => $student,
            'date' => $date,
            'today' => $this->amol->today(),
            'checklist' => $this->amol->checklistFor($student, $date),
            'note' => $this->amol->noteFor($student, $date),
            'stats' => $this->stats->forStudent($student, AmolDate::resolveMonth($request->query('month')), $this->amol->today()),
        ]);
    }

    public function destroyNote(Request $request, User $student, AuditLogger $audit): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $date = AmolDate::resolve($request->input('date'));

        if ($this->amol->deleteNote($student, $date)) {
            $audit->log('amol.note_deleted', $student, null, ['date' => $date->toDateString()], $request->user());
        }

        return redirect()->route('admin.amol.show', ['student' => $student, 'date' => $date->toDateString()])
            ->with('success', __('amol.note_deleted'));
    }

    public function saveNote(AmolNoteRequest $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $data = $request->validated();

        $this->amol->saveNote(
            $student,
            CarbonImmutable::createFromFormat('Y-m-d', $data['date'])->startOfDay(),
            $data['note'],
            $request->user(),
        );

        return redirect()->route('admin.amol.show', ['student' => $student, 'date' => $data['date']])
            ->with('success', 'মন্তব্য সংরক্ষণ করা হয়েছে।');
    }
}
