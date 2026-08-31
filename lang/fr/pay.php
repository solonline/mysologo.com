<?php

return [
    'title'        => 'Paiement — :merchant',
    'test_mode'    => 'Mode test — aucun débit réel n\'est effectué',
    'requested_by' => 'Paiement demandé par',
    'recipient'    => 'Destinataire',
    'items'        => 'Articles',

    'status' => [
        'completed' => 'Paiement effectué',
        'completed_hint' => 'Merci. Votre paiement a bien été enregistré.',
        'failed'    => 'Paiement échoué. Veuillez réessayer.',
        'expired'   => 'Ce lien a expiré.',
        'cancelled' => 'Ce lien a été annulé.',
    ],

    'confirming'  => 'Confirmation du paiement…',
    'pay_button'  => 'Payer :amount :currency',
    'processing'  => 'Traitement en cours…',
    'valid_until' => 'Lien valable jusqu\'au :date',
    'secure'      => 'Paiement sécurisé via',
];
