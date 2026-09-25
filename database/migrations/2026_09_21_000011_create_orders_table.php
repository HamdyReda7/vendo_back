<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping', 10, 2)->nullable();
            $table->decimal('total', 10, 2);
            $table->string('governorate');
            $table->text('address');
            $table->string('delivery_phone')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
