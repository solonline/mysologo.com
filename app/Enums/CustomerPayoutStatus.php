<?php

namespace App\Enums;

enum CustomerPayoutStatus: string
{
    case PENDING    = 'pending';    // aguarda distribuição
    case PROCESSING = 'processing'; // chamada ao terceiro em curso
    case COMPLETED  = 'completed';  // IBAN creditado
    case FAILED     = 'failed';     // falha — pode ser retentado
    case SKIPPED    = 'skipped';    // sem IBAN configurado, saldo mantido na plataforma
}
