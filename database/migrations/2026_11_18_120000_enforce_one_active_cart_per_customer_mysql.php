<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-013: At most one active customer-owned cart per customer (MySQL InnoDB only).
 *
 * Uses a VIRTUAL generated column that equals customer_id only while status is active;
 * UNIQUE on that column allows multiple NULLs (guest / non-active carts).
 *
 * VIRTUAL (not STORED): MySQL 9.x rejects STORED expressions that reference columns
 * participating in foreign keys (errno 1215); VIRTUAL preserves the same uniqueness semantics.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $duplicateCustomerIds = DB::table('carts')
            ->select('customer_id')
            ->where('status', 'active')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('customer_id');

        if ($duplicateCustomerIds->isNotEmpty()) {
            throw new RuntimeException(
                'Migration aborted (DB-013): multiple active carts exist for customer_id(s): '
                .$duplicateCustomerIds->implode(', ')
                .'. Remediate manually (do not auto-merge or delete). See docs/DATABASE_DESIGN.md.'
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE carts
            ADD COLUMN active_customer_claim BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    IF(`status` = 'active' AND `customer_id` IS NOT NULL, `customer_id`, NULL)
                ) VIRTUAL,
            ADD UNIQUE INDEX carts_active_customer_claim_unique (active_customer_claim)
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('carts', 'active_customer_claim')) {
            return;
        }

        DB::statement('ALTER TABLE carts DROP INDEX carts_active_customer_claim_unique');
        DB::statement('ALTER TABLE carts DROP COLUMN active_customer_claim');
    }
};
