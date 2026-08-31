<?php

return [
    'title'        => 'Pagamento — :merchant',
    'test_mode'    => 'Modalità test — non vengono effettuati addebiti reali',
    'requested_by' => 'Pagamento richiesto da',
    'recipient'    => 'Destinatario',
    'items'        => 'Articoli',

    'status' => [
        'completed' => 'Pagamento completato',
        'completed_hint' => 'Grazie. Il pagamento è stato registrato correttamente.',
        'failed'    => 'Pagamento fallito. Riprova.',
        'expired'   => 'Questo link è scaduto.',
        'cancelled' => 'Questo link è stato annullato.',
    ],

    'confirming'  => 'Conferma pagamento…',
    'pay_button'  => 'Paga :amount :currency',
    'processing'  => 'Elaborazione…',
    'valid_until' => 'Link valido fino al :date',
    'secure'      => 'Pagamento sicuro tramite',
];
