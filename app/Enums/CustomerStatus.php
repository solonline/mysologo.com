<?php
namespace App\Enums;

enum CustomerStatus: string
{
    case PENDING = 'PENDING';
    case ANALYSIS = 'ANALYSIS';
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case BLOCKED = 'BLOCKED';
}
