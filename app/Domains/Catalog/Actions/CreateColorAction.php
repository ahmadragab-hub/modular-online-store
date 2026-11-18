<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Color;
use Illuminate\Support\Str;

final class CreateColorAction
{
    public function execute(string $name, ?string $slug = null): Color
    {
        return Color::query()->create([
            'name' => trim($name),
            'slug' => Str::slug($slug !== null && trim($slug) !== '' ? $slug : $name),
        ]);
    }
}
