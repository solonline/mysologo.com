<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject_id', is_numeric($search) ? $search : null)
                  ->orWhereHasMorph('causer', [User::class, Customer::class],
                      fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($log = $request->input('log')) {
            $query->inLog($log);
        }

        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->latest()->paginate(25)->withQueryString();

        return view('dashboard.audit-logs.index', compact('logs'));
    }

    public function show(Activity $activity)
    {
        $activity->load(['causer', 'subject']);

        return view('dashboard.audit-logs.show', compact('activity'));
    }
}
