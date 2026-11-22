<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class BankTransferQr
{
    /** @return array<string, string>|null */
    public function details(): ?array
    {
        $details = app(Checkout::class)->bankDetails();
        $bin = config('checkout.bank_transfer.bank_bin');
        if ($details === null || ! is_string($bin) || ! preg_match('/\A[0-9]{6}\z/', $bin)
            || ! preg_match('/\A[a-zA-Z0-9]{1,34}\z/', $details['account_number'])) {
            return null;
        }

        return [...$details, 'bank_bin' => $bin];
    }

    /** @param array<string, string> $bank */
    public function payload(array $bank, int $amount, string $memo): string
    {
        $recipient = $this->field('00', $bank['bank_bin']).$this->field('01', $bank['account_number']);
        $merchant = $this->field('00', 'A000000727').$this->field('01', $recipient).$this->field('02', 'QRIBFTTA');
        $payload = $this->field('00', '01').$this->field('01', '12').$this->field('38', $merchant)
            .$this->field('53', '704').$this->field('54', (string) $amount).$this->field('58', 'VN')
            .$this->field('62', $this->field('08', $memo)).'6304';
        $crc = 0xFFFF;
        foreach (str_split($payload) as $character) {
            $crc ^= ord($character) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1) & 0xFFFF;
            }
        }

        return $payload.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /** @param array<string, string> $bank */
    public function image(array $bank, int $amount, string $memo): string
    {
        if ($bank['account_number'] === config('checkout.shared_qr.account_number')
            && (($bank['bank_bin'] ?? null) === config('checkout.shared_qr.bank_bin') || str_contains(strtolower($bank['bank_name']), 'vietinbank'))) {
            return asset(config('checkout.shared_qr.image'));
        }
        $writer = new Writer(new ImageRenderer(new RendererStyle(280, 4), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($this->payload($bank, $amount, $memo)));
    }

    /** @param array<string, string> $bank */
    public function forSnapshot(array $bank, int $amount, string $memo): ?string
    {
        if ($bank['account_number'] === config('checkout.shared_qr.account_number') && str_contains(strtolower($bank['bank_name']), 'vietinbank')) {
            return asset(config('checkout.shared_qr.image'));
        }

        return isset($bank['bank_bin']) ? $this->image($bank, $amount, $memo) : null;
    }

    private function field(string $id, string $value): string
    {
        if (strlen($value) > 99) {
            throw new \InvalidArgumentException('QR field is too long.');
        }

        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }
}
