<?php

namespace App\Services;

use App\Models\TableInvoice;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class AdminCounterConfirmation
{
    public function issue(User $actor, TableInvoice $invoice, string $target, int $revision, ?string $reason, bool $refundConfirmed = false): string
    {
        return Crypt::encryptString(json_encode([
            'actor_id' => $actor->id, 'invoice_id' => $invoice->id, 'target' => $target,
            'revision' => $revision, 'reason' => $reason, 'payment_status' => $invoice->status, 'deposit_refund_confirmed' => $refundConfirmed, 'expires_at' => now()->addMinutes(10)->timestamp,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @return array{target: string, revision: int, reason: ?string, payment_status: string, deposit_refund_confirmed: bool} */
    public function read(User $actor, TableInvoice $invoice, string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw $this->invalid();
        }
        if (! is_array($payload)) {
            throw $this->invalid();
        }
        $data = Validator::make($payload, [
            'actor_id' => ['required', 'integer', Rule::in([$actor->id])],
            'invoice_id' => ['required', 'integer', Rule::in([$invoice->id])],
            'target' => ['required', Rule::in(['completed', 'cancelled'])],
            'revision' => ['required', 'integer', 'min:1'],
            'payment_status' => ['required', Rule::in(['draft', 'paid'])],
            'deposit_refund_confirmed' => ['required', 'boolean'],
            'reason' => ['present', 'nullable', 'string', 'max:500', 'required_if:target,cancelled', 'required_if:payment_status,paid'],
            'expires_at' => ['required', 'integer', 'gt:'.now()->timestamp],
        ]);
        if ($data->fails()) {
            throw $this->invalid();
        }

        return ['target' => $payload['target'], 'revision' => $payload['revision'], 'reason' => $payload['reason'], 'payment_status' => $payload['payment_status'], 'deposit_refund_confirmed' => $payload['deposit_refund_confirmed']];
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['confirmation_token' => 'Xác minh không hợp lệ hoặc đã hết hạn. Vui lòng chọn lại trạng thái và xác minh.']);
    }
}
