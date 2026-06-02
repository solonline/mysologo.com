<?php

namespace App\Enums;

enum SettlementBatchStatus: string
{
    case PENDING      = 'pending';       // criado, aguarda transferência master
    case TRANSFERRED  = 'transferred';   // fundos enviados para conta master
    case DISTRIBUTING = 'distributing';  // a distribuir para IBANs individuais
    case COMPLETED    = 'completed';     // todos os payouts concluídos
    case FAILED       = 'failed';        // falha na transferência master
}
