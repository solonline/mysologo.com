<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Queries\GetAuditLogDetailQuery;
use App\Domains\Backoffice\Queries\ListAuditLogsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function __construct(
        protected ListAuditLogsQuery $listLogs,
        protected GetAuditLogDetailQuery $logDetail,
    ) {}

    public function index(Request $request)
    {
        return view('dashboard.audit-logs.index', [
            'logs' => $this->listLogs->execute($request->only([
                'search', 'log', 'event', 'from', 'to',
            ]))->withQueryString(),
        ]);
    }

    public function show(Activity $activity)
    {
        return view('dashboard.audit-logs.show', [
            'activity' => $this->logDetail->execute($activity),
        ]);
    }
}
