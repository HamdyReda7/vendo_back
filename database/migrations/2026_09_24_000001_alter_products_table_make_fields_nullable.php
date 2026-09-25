<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description_ar')->nullable()->change();
            $table->text('description_en')->nullable()->change();
            $table->unsignedInteger('quantity')->nullable()->default(0)->change();
            $table->boolean('status')->nullable()->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description_ar')->nullable(false)->change();
            $table->text('description_en')->nullable(false)->change();
            $table->unsignedInteger('quantity')->nullable(false)->default(0)->change();
            $table->boolean('status')->nullable(false)->default(true)->change();
        });
    }
};
