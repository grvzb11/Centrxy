<?php

return [
    // Provide verified details in .env before public distribution. Do NOT invent an operator.
    'operator' => env('CERTIFY_LEGAL_OPERATOR', 'grvzb'),
    'contact_email' => env('CERTIFY_LEGAL_CONTACT_EMAIL', 'grvzb11@gmail.com'),
    'region' => env('CERTIFY_LEGAL_REGION', ''),
    'effective_date' => env('CERTIFY_LEGAL_EFFECTIVE_DATE', ''),
];
