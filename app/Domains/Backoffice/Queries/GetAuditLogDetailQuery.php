<?php

namespace App\Domains\Backoffice\Queries;

use Spatie\Activitylog\Models\Activity;

class GetAuditLogDetailQuery
{
    public function execute(Activity $activity): Activity
    {
        return $activity->load(['causer', 'subject']);
    }
}
