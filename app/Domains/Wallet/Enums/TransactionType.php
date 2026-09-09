<?php

namespace App\Domains\Wallet\Enums;

enum TransactionType: string
{
    case CARD = 'card';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case CARD_PRESENT = 'card_present';
    case BANK_TRANSFER = 'bank_transfer';
    case SEPA_DEBIT = 'sepa_debit';
    case US_BANK_ACCOUNT = 'us_bank_account';
    case ACSS_DEBIT = 'acss_debit';
    case BECS_DEBIT = 'becs_debit';
    case MULTIBANCO = 'multibanco';
    case MBWAY = 'mb_way';
    case IDEAL = 'ideal';
    case BANCONTACT = 'bancontact';
    case EPS = 'eps';
    case P24 = 'p24';
    case GIROPAY = 'giropay';
    case SOFORT = 'sofort';
    case TWINT = 'twint';
    case KLARNA = 'klarna';
    case AFTERPAY_CLEARPAY = 'afterpay_clearpay';
    case PAYPAL = 'paypal';
    case LINK = 'link';
    case REVOLUT_PAY = 'revolut_pay';
    case AMAZON_PAY = 'amazon_pay';
    case CASHAPP = 'cashapp';
    case BOLETO = 'boleto';
    case OXXO = 'oxxo';
    case ALIPAY = 'alipay';
    case WECHAT_PAY = 'wechat_pay';
    case GRABPAY = 'grabpay';
    case P2P = 'p2p';
    case TOPUP = 'topup';
    case MERCHANT_PAYMENT = 'merchant_payment';
    case CUSTOMER_BALANCE = 'customer_balance';

    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Cartão',
            self::CREDIT_CARD => 'Cartão de Crédito',
            self::DEBIT_CARD => 'Cartão de Débito',
            self::CARD_PRESENT => 'Cartão Físico (Terminal)',
            self::BANK_TRANSFER => 'Transferência Bancária',
            self::SEPA_DEBIT => 'SEPA Direct Debit',
            self::US_BANK_ACCOUNT => 'ACH Direct Debit',
            self::ACSS_DEBIT => 'Pre-authorized Debit',
            self::BECS_DEBIT => 'BECS Direct Debit',
            self::MULTIBANCO => 'Multibanco',
            self::MBWAY => 'MB WAY',
            self::IDEAL => 'iDEAL',
            self::BANCONTACT => 'Bancontact',
            self::EPS => 'EPS',
            self::P24 => 'Przelewy24',
            self::GIROPAY => 'Giropay',
            self::SOFORT => 'SOFORT',
            self::TWINT => 'TWINT',
            self::KLARNA => 'Klarna',
            self::AFTERPAY_CLEARPAY => 'Afterpay / Clearpay',
            self::PAYPAL => 'PayPal',
            self::LINK => 'Link by Stripe',
            self::REVOLUT_PAY => 'Revolut Pay',
            self::AMAZON_PAY => 'Amazon Pay',
            self::CASHAPP => 'Cash App Pay',
            self::BOLETO => 'Boleto',
            self::OXXO => 'OXXO',
            self::ALIPAY => 'Alipay',
            self::WECHAT_PAY => 'WeChat Pay',
            self::GRABPAY => 'GrabPay',
            self::P2P => 'Transferência entre contas',
            self::TOPUP => 'Carregamento',
            self::MERCHANT_PAYMENT => 'Venda SecurePay',
            self::CUSTOMER_BALANCE => 'Saldo do Cliente',
        };
    }
}
