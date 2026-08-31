<?php

return [
    'title'        => 'Zahlung — :merchant',
    'test_mode'    => 'Testmodus — es werden keine echten Abbuchungen vorgenommen',
    'requested_by' => 'Zahlung angefordert von',
    'recipient'    => 'Empfänger',
    'items'        => 'Artikel',

    'status' => [
        'completed' => 'Zahlung abgeschlossen',
        'completed_hint' => 'Danke. Ihre Zahlung wurde erfolgreich erfasst.',
        'failed'    => 'Zahlung fehlgeschlagen. Bitte erneut versuchen.',
        'expired'   => 'Dieser Link ist abgelaufen.',
        'cancelled' => 'Dieser Link wurde storniert.',
    ],

    'confirming'  => 'Zahlung wird bestätigt…',
    'pay_button'  => ':amount :currency bezahlen',
    'processing'  => 'Wird verarbeitet…',
    'valid_until' => 'Link gültig bis :date',
    'secure'      => 'Sichere Zahlung über',
];
