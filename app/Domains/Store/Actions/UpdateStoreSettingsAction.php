<?php

namespace App\Domains\Store\Actions;

use App\Domains\Store\Exceptions\StoreAdminException;
use App\Domains\Store\Models\StoreSetting;
use Illuminate\Support\Facades\DB;

final class UpdateStoreSettingsAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): StoreSetting
    {
        return DB::transaction(function () use ($attributes): StoreSetting {
            /** @var StoreSetting|null $settings */
            $settings = StoreSetting::query()->whereKey(StoreSetting::SINGLETON_ID)->lockForUpdate()->first();

            if ($settings === null) {
                throw new StoreAdminException(
                    StoreAdminException::SETTINGS_NOT_FOUND,
                    'Store settings not configured.',
                );
            }

            $settings->fill($attributes);
            $settings->save();

            return $settings->fresh();
        });
    }
}
