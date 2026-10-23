<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PointController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $sort = \App\Support\TableSort::from(request(), ['date', 'reason', 'amount', 'balance'], 'date', 'desc');

        return view('student.points', [
            'user' => $user,
            'sort' => $sort,
            'transactions' => $sort->query($user->pointTransactions()->with('performedBy')->getQuery(), [
            // same timestamp → the later row (higher id) counts as later
            'date' => fn ($q, $d) => $q->orderBy('created_at', $d)->orderBy('id', $d),
            'type' => 'type',
            'reason' => 'reason',
            'who' => fn ($q, $d) => $q->orderByRaw('(SELECT name FROM users WHERE users.id = point_transactions.performed_by) IS NULL')
                ->orderByRaw('(SELECT name FROM users WHERE users.id = point_transactions.performed_by) '.$d),
            'amount' => fn ($q, $d) => $q->orderBy('amount', $d)->orderBy('id', 'desc'),
            'balance' => fn ($q, $d) => $q->orderBy('balance_after', $d)->orderBy('id', 'desc'),
        ])
                ->paginate(20)->withQueryString(),
        ]);
    }
}
