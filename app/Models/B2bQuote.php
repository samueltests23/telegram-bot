<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2bQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_chat_id',
        'company_name',
        'rif',
        'employees_count',
        'area_interest',
        'status',
    ];
}