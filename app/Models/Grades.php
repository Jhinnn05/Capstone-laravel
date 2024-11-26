<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grades extends Model
{
    use HasFactory;
    protected $table = 'enrollments';
    protected $primaryKey = 'enrol_id'; // Specify the primary key
    public $incrementing = false; // Set to false if guardian_id is not an auto-incrementing integer
    protected $keyType = 'string'; // Set the key type if it's not an integer (e.g., if it's a string)
    protected $fillable =[
        'LRN',
        
    ];
    public function student()
    {   
        // return $this->hasMany(Student::class, 'guardian_id'); 
        return $this->belongsTo(Student::class, 'LRN', 'LRN');
    }
}
