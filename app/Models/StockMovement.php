<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_sku_id', 'type', 'quantity', 'previous_stock', 'new_stock',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];

    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
