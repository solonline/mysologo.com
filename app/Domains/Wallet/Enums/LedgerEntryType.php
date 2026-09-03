<?php

namespace App\Domains\Wallet\Enums;

enum LedgerEntryType: string
{
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_FEE = 'payment_fee';
    case PLATFORM_FEE = 'platform_fee';
    case PAYMENT_REFUND = 'payment_refund';
    case P2P_SEND = 'p2p_send';
    case P2P_RECEIVE = 'p2p_receive';
    case CARD_LOAD = 'card_load';
    case CARD_UNLOAD = 'card_unload';
    case CARD_PURCHASE = 'card_purchase';
    case SETTLEMENT = 'settlement';
    case PARTNER_FUNDING_RELEASED = 'partner_funding_released';
    case MANUAL_CREDIT = 'manual_credit';
    case MANUAL_DEBIT = 'manual_debit';
    case VIRTUAL_CARD_ISSUANCE_FEE = 'virtual_card_issuance_fee';

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT_RECEIVED => 'Pagamento Recebido',
            self::PAYMENT_FEE => 'Taxa de Pagamento',
            self::PLATFORM_FEE => 'Taxa da Plataforma',
            self::PAYMENT_REFUND => 'Reembolso',
            self::P2P_SEND => 'Transferência Enviada',
            self::P2P_RECEIVE => 'Transferência Recebida',
            self::CARD_LOAD => 'Carregamento de Cartão',
            self::CARD_UNLOAD => 'Estorno de Cartão',
            self::CARD_PURCHASE => 'Compra com Cartão',
            self::SETTLEMENT => 'Liquidação',
            self::PARTNER_FUNDING_RELEASED => 'Libertação de Funding Parceiro',
            self::MANUAL_CREDIT => 'Crédito Manual',
            self::MANUAL_DEBIT => 'Débito Manual',
            self::VIRTUAL_CARD_ISSUANCE_FEE => 'Taxa de Emissão de Cartão Virtual',
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
            self::MANUAL_CREDIT => TransactionDirection::Credit,
            self::PAYMENT_FEE,
            self::PLATFORM_FEE,
            self::P2P_SEND,
            self::CARD_LOAD,
            self::CARD_PURCHASE,
            self::SETTLEMENT,
            self::MANUAL_DEBIT,
            self::VIRTUAL_CARD_ISSUANCE_FEE => TransactionDirection::Debit,
        };
    }
}
