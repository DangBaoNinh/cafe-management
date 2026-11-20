<?php

return [
    'bank_transfer' => [
        'bank_name' => env('CHECKOUT_BANK_NAME'),
        'account_number' => env('CHECKOUT_BANK_ACCOUNT_NUMBER'),
        'account_holder' => env('CHECKOUT_BANK_ACCOUNT_HOLDER'),
    ],
];
