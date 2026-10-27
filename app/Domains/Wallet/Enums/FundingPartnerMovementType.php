<?php

namespace App\Domains\Wallet\Enums;

enum FundingPartnerMovementType: string
{
    case AccountTransfer = 'account_transfer';
    case CardPurchase = 'card_purchase';
    case CardWithdrawal = 'card_withdrawal';
    case CardWithdrawalFee = 'card_withdrawal_fee';
    case CardRefund = 'card_refund';
    case InboundPayment = 'inbound_payment';
    case Fee = 'fee';
    case VirtualCardIssuanceFee = 'virtual_card_issuance_fee';
    case PhysicalCardIssuanceFee = 'physical_card_issuance_fee';
}
