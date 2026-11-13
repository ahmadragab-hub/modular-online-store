<?php

namespace App\Console\Commands;

use App\Domains\Inventory\Actions\ExpireReservationsAction;
use App\Domains\Inventory\DTOs\ExpireReservationsInput;
use Illuminate\Console\Command;

final class ExpireInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:expire-reservations
                            {--limit=100 : Maximum reservations to process in this run}';

    protected $description = 'Expire active inventory reservations past their TTL';

    public function handle(ExpireReservationsAction $action): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1) {
            $this->error('Option --limit must be a positive integer.');

            return self::FAILURE;
        }

        $result = $action->execute(new ExpireReservationsInput(batchLimit: $limit));

        $this->info(sprintf(
            'Expire run complete: %d selected, %d expired, %d skipped, %d failed.',
            $result->candidatesSelected,
            $result->expiredCount,
            $result->skippedCount,
            $result->failedCount,
        ));

        return self::SUCCESS;
    }
}
