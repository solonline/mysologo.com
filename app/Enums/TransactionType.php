<?php

namespace App\Enums;

enum TransactionType: string
{
    case Stripe = 'stripe';
    case P2P    = 'p2p';
}
