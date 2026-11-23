<?php

namespace Tests\Feature;

use App\Services\Checkout;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SharedPaymentQrTest extends TestCase
{
    public function test_common_payment_qr_shows_the_exact_amount_and_reference(): void
    {
        $bank = app(Checkout::class)->bankDetails();
        $this->assertSame('100876266786', $bank['account_number']);
        $html = Blade::render('<x-payment-qr :amount="46000" memo="HD0000012" />');
        $this->assertStringContainsString('/payments/transfer-qr.png', $html);
        $this->assertStringContainsString('46.000 ₫', $html);
        $this->assertStringContainsString('HD0000012', $html);
        $this->assertStringContainsString('Nhập đúng số tiền', $html);
    }

    public function test_old_bank_snapshots_do_not_display_an_unrelated_recipient_qr(): void
    {
        $html = Blade::render('<x-payment-qr :amount="40000" memo="OLD-INVOICE" :bank="$bank" />', [
            'bank' => ['bank_name' => 'Ngân hàng cũ', 'account_number' => '123456789', 'account_holder' => 'QUAN CU'],
        ]);
        $this->assertStringNotContainsString('/payments/transfer-qr.png', $html);
    }
}
