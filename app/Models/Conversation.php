<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_chat_id',
        'user_name',
        'status',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}