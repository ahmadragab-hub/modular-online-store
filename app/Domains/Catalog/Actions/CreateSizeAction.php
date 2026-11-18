<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Size;
use Illuminate\Support\Str;

final class CreateSizeAction
{
    public function execute(string $label, ?string $code = null): Size
    {
        $code = $code !== null && trim($code) !== ''
            ? strtoupper(trim($code))
            : strtoupper(Str::slug($label, '-'));

        return Size::query()->create([
            'code' => $code,
            'label' => trim($label),
        ]);
    }
}
