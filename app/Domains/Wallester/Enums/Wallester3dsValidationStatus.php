<?php

namespace App\Domains\Wallester\Enums;

enum Wallester3dsValidationStatus: string
{
    case Success = 'Success';
    case Pending = 'Pending';
    case Rejected = 'Rejected';
    case Error = 'Error';
}
