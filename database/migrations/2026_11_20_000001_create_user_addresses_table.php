<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('recipient_name', 120);
            $table->string('phone', 13);
            $table->char('province_code', 2);
            $table->char('district_code', 3);
            $table->char('ward_code', 5)->nullable();
            $table->string('address_line');
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_user_id')
                ->nullable()
                ->virtualAs('CASE WHEN is_default = 1 THEN user_id ELSE NULL END');
            $table->timestamps();

            $table->index('user_id');
            $table->unique('default_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
