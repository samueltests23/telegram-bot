<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['training_area_id', 'title', 'duration', 'purpose', 'certification'];

    public function area()
    {
        return $this->belongsTo(TrainingArea::class, 'training_area_id');
    }
}