<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['restaurant_id', 'name', 'image', 'status'])]
class Category extends Model
{
    /**
     * Get the restaurant that owns the category.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Get the products of the category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
