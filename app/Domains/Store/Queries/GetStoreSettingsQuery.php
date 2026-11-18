<?php

namespace App\Domains\Store\Queries;

use App\Domains\Store\Exceptions\StoreAdminException;
use App\Domains\Store\Models\StoreSetting;

final class GetStoreSettingsQuery
{
    public function execute(): StoreSetting
    {
        $settings = StoreSetting::singleton();

        if ($settings === null) {
            throw new StoreAdminException(
                StoreAdminException::SETTINGS_NOT_FOUND,
                'Store settings not configured.',
            );
        }

        return $settings;
    }
}
