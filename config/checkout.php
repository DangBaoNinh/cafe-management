<?php

return [
    'customer_refund_demo' => (bool) env('CUSTOMER_REFUND_DEMO_ENABLED', true),
    'reservation_deposit_demo' => (bool) env('RESERVATION_DEPOSIT_DEMO_ENABLED', false),
    'bank_transfer' => [
        // Recipient supplied by the shop in docs/ui-preview/image.png (public payment information).
        'bank_name' => env('CHECKOUT_BANK_NAME') ?: 'VietinBank',
        'bank_bin' => env('CHECKOUT_BANK_BIN') ?: '970415',
        'account_number' => env('CHECKOUT_BANK_ACCOUNT_NUMBER') ?: '100876266786',
        'account_holder' => env('CHECKOUT_BANK_ACCOUNT_HOLDER') ?: 'NGUYEN QUANG THANH TOAN',
    ],
    'shared_qr' => ['image' => 'payments/transfer-qr.png', 'account_number' => '100876266786', 'bank_bin' => '970415'],
];
