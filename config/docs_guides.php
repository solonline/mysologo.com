<?php

return [

    /*
    | Guias conceituais — ficheiros em docs/guides/
    | audience: internal | partners
    */

    'internal' => [
        [
            'slug' => 'getting-started',
            'title' => 'Primeiros passos',
            'description' => 'Autenticação, token e primeira chamada à API.',
            'file' => 'getting-started.md',
        ],
        [
            'slug' => 'auth-session',
            'title' => 'AuthSession — campos JSON',
            'description' => 'Referência de cada chave em login, registo e login-pin.',
            'file' => 'auth-session.md',
        ],
        [
            'slug' => 'onboarding-lifecycle',
            'title' => 'Ciclo de vida da conta',
            'description' => 'Estados desde o registo até ACTIVE e integrações.',
            'file' => 'onboarding-lifecycle.md',
        ],
        [
            'slug' => 'account-ready',
            'title' => 'Conta pronta a usar',
            'description' => 'Checklist: quando o customer pode transaccionar.',
            'file' => 'account-ready.md',
        ],
        [
            'slug' => 'topup-card',
            'title' => 'Top-up com cartão',
            'description' => 'PaymentIntent, client_secret, webhook e saldo.',
            'file' => 'topup-card.md',
        ],
        [
            'slug' => 'p2p-transfer',
            'title' => 'Transferência P2P',
            'description' => 'Enviar dinheiro entre utilizadores com PIN.',
            'file' => 'p2p-transfer.md',
        ],
        [
            'slug' => 'webhooks',
            'title' => 'Webhooks (inbound)',
            'description' => 'Didit, Stripe, Ebury e Wallester — o que cada um altera.',
            'file' => 'webhooks.md',
        ],
        [
            'slug' => 'wallester-card-movements',
            'title' => 'Compras Wallester e câmbio',
            'description' => 'Um movimento por compra: total na conta, valor original e taxa FX.',
            'file' => 'wallester-card-movements.md',
        ],
    ],

    'partners' => [
        [
            'slug' => 'payment-requests',
            'title' => 'Payment requests',
            'description' => 'Criar links de pagamento para clientes finais.',
            'file' => 'partners/payment-requests.md',
        ],
        [
            'slug' => 'terminal-topup',
            'title' => 'Terminal (POS)',
            'description' => 'Connection token e intent no TPA.',
            'file' => 'partners/terminal-topup.md',
        ],
        [
            'slug' => 'ai-prompt',
            'title' => 'Prompt para IA',
            'description' => 'Texto pronto a colar num assistente para integrar a API.',
            'file' => 'partners/ai-prompt.md',
        ],
    ],

];
