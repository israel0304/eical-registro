<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stand extends Model
{
    protected $fillable = [
        'name', 'location', 'stand_type_id', 'activity_name',
        'activity_category', 'project_video_url', 'uses_recycled_canvas',
        'brings_own_equipment',
    ];

    public function standType()
    {
        return $this->belongsTo(StandType::class);
    }

    public function qrCode()
    {
        return $this->morphOne(QrCode::class, 'codeable');
    }
}
