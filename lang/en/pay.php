<?php

return [
    'title'        => 'Payment — :merchant',
    'test_mode'    => 'Test Mode — no real charges are made',
    'requested_by' => 'Payment requested by',
    'recipient'    => 'Recipient',
    'items'        => 'Items',

    'status' => [
        'completed'  => 'Payment completed',
        'completed_hint' => 'Thank you. Your payment was registered successfully.',
        'failed'     => 'Payment failed. Please try again.',
        'expired'    => 'This link has expired.',
        'cancelled'  => 'This link was cancelled.',
    ],

    'confirming'    => 'Confirming payment…',
    'pay_button'    => 'Pay :amount :currency',
    'processing'    => 'Processing…',
    'valid_until'   => 'Link valid until :date',
    'secure'        => 'Secure payment via',
];
