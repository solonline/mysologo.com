<?php
namespace App\Enums;

enum DocumentStatus: string
{
    case PENDING = 'PENDING';
    case ANALYSING = 'ANALYSING';
    case APPROVED = 'APPROVED';
    case DECLINED = 'DECLINED';
}
