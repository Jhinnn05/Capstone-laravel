<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;
    
    protected $table = 'messages'; // Specify the table name if it differs
    protected $fillable = [
        'message_id',
        'message_sender', 
        'message_reciever', 
        'message',
        'message_date',
        'created_at',
        'updated_at',
    ];
}
