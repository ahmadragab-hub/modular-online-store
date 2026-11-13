<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sizes', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('label', 64);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->char('hex_code', 7)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('brand', 128)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('product_translations', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->char('locale', 5);
            $table->string('name');
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);

            $table->foreign('locale')
                ->references('code')
                ->on('store_locales')
                ->restrictOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->foreignId('size_id')->constrained('sizes')->restrictOnDelete();
            $table->foreignId('color_id')->constrained('colors')->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->unique(['product_id', 'size_id', 'color_id']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('disk', 32);
            $table->string('path', 512);
            $table->string('alt_text')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->index('product_variant_id');
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('sizes');
    }
};
