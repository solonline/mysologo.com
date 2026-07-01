<?php

namespace App\Domains\Wallester\Enums;

enum Wallester3dsChallengeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
