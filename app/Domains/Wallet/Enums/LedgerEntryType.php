<?php

namespace App\Domains\Wallet\Enums;

enum LedgerEntryType: string
{
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_FEE = 'payment_fee';
    case PLATFORM_FEE = 'platform_fee';
    case PLATFORM_FEE_INCOME = 'platform_fee_income';
    case PAYMENT_REFUND = 'payment_refund';
    case P2P_SEND = 'p2p_send';
    case P2P_RECEIVE = 'p2p_receive';
    case CARD_LOAD = 'card_load';
    case CARD_UNLOAD = 'card_unload';
    case CARD_PURCHASE = 'card_purchase';
    case CARD_WITHDRAWAL = 'card_withdrawal';
    case CARD_WITHDRAWAL_FEE = 'card_withdrawal_fee';
    case SETTLEMENT = 'settlement';
    case PARTNER_FUNDING_RELEASED = 'partner_funding_released';
    case MANUAL_CREDIT = 'manual_credit';
    case MANUAL_DEBIT = 'manual_debit';
    case VIRTUAL_CARD_ISSUANCE_FEE = 'virtual_card_issuance_fee';
    case PHYSICAL_CARD_ISSUANCE_FEE = 'physical_card_issuance_fee';
    case WISE_TRANSFER = 'wise_transfer';

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT_RECEIVED => 'Pagamento Recebido',
            self::PAYMENT_FEE => 'Taxa de Pagamento',
            self::PLATFORM_FEE => 'Taxa da Plataforma',
            self::PLATFORM_FEE_INCOME => 'Câmbio Yoop (receita)',
            self::PAYMENT_REFUND => 'Reembolso',
            self::P2P_SEND => 'Transferência Enviada',
            self::P2P_RECEIVE => 'Transferência Recebida',
            self::CARD_LOAD => 'Carregamento de Cartão',
            self::CARD_UNLOAD => 'Estorno de Cartão',
            self::CARD_PURCHASE => 'Compra com Cartão',
            self::CARD_WITHDRAWAL => 'Saque',
            self::CARD_WITHDRAWAL_FEE => 'Taxa de saque',
            self::SETTLEMENT => 'Liquidação',
            self::PARTNER_FUNDING_RELEASED => 'Libertação de Funding Parceiro',
            self::MANUAL_CREDIT => 'Crédito Yoop',
            self::MANUAL_DEBIT => 'Débito Yoop',
            self::VIRTUAL_CARD_ISSUANCE_FEE => 'Taxa de Emissão de Cartão Virtual',
            self::PHYSICAL_CARD_ISSUANCE_FEE => 'Taxa de Emissão de Cartão Físico',
            self::WISE_TRANSFER => 'Transferência Wise',
        };
    }

    public function direction(): TransactionDirection
    {
        return match ($this) {
            self::PAYMENT_RECEIVED,
            self::PAYMENT_REFUND,
            self::P2P_RECEIVE,
            self::CARD_UNLOAD,
            self::PARTNER_FUNDING_RELEASED,
            self::PLATFORM_FEE_INCOME,
            self::MANUAL_CREDIT => TransactionDirection::Credit,
            self::PAYMENT_FEE,
            self::PLATFORM_FEE,
            self::P2P_SEND,
            self::CARD_LOAD,
            self::CARD_PURCHASE,
            self::CARD_WITHDRAWAL,
            self::CARD_WITHDRAWAL_FEE,
            self::SETTLEMENT,
            self::MANUAL_DEBIT,
            self::VIRTUAL_CARD_ISSUANCE_FEE,
            self::PHYSICAL_CARD_ISSUANCE_FEE,
            self::WISE_TRANSFER => TransactionDirection::Debit,
        };
    }
}
