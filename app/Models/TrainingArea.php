<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingArea extends Model
{
    protected $fillable = ['slug', 'name', 'icon'];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}