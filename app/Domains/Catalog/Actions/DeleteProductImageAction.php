<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Exceptions\CatalogAdminException;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

final class DeleteProductImageAction
{
    public function execute(ProductImage $image): void
    {
        if ($image->disk === 'public' && is_string($image->path) && $image->path !== '') {
            Storage::disk('public')->delete($image->path);
        }

        $deleted = $image->delete();

        if (! $deleted) {
            throw new CatalogAdminException(
                CatalogAdminException::NOT_FOUND,
                'Image could not be deleted.',
            );
        }
    }
}
