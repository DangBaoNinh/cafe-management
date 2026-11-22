<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')->where('status', 'confirmed')->where('scheduled_at', '>=', now('Asia/Ho_Chi_Minh')->startOfDay()->addDay()->utc())->update(['active_seat_id' => null]);
        Schema::table('users', fn (Blueprint $table) => $table->text('linked_bank')->nullable());
        Schema::create('customer_refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_deposit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_vnd');
            $table->text('bank_snapshot');
            $table->string('status')->default('pending');
            $table->string('refund_reference', 128)->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('customer_refund_requests')->exists()) {
            throw new RuntimeException('Không xóa schema khi có yêu cầu hoàn tiền.');
        }
        Schema::dropIfExists('customer_refund_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('linked_bank'));
    }
};
