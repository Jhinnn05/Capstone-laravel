<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tuition extends Model
{
    use HasFactory;
    
    protected $table = 'tuitions';
    protected $fillable = [
        'grade_level',           
        'tuition',     
        'general',  
        'esc',  
        'subsidy', 
        'req_Downpayment'
    ];
}