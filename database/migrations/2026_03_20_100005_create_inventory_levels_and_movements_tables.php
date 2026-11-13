<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('type', 32);
            $table->integer('quantity_delta');
            $table->integer('quantity_on_hand_after');
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['product_variant_id', 'created_at'], 'inventory_movements_variant_created_idx');
            $table->index(['reference_type', 'reference_id'], 'inventory_movements_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_levels');
    }
};
