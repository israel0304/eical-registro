<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRequest extends Model
{
    protected $fillable = [
        'stand_id', 'sku_id', 'quantity', 'status', 'type',
        'custom_description', 'requested_at', 'delivered_at',
        'staff_id', 'cancelled_at', 'cancelled_by', 'approved_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function stand()
    {
        return $this->belongsTo(Stand::class);
    }

    public function sku()
    {
        return $this->belongsTo(ProductSku::class, 'sku_id');
    }

    public function movements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
