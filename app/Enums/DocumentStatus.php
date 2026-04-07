<?php
namespace App\Enums;

enum DocumentStatus: string
{
    case PENDING = 'PENDING';
    case ANALYSIS = 'ANALYSIS';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
}
