<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;
    protected $table = 'enrollments';
    protected $primaryKey = 'enrol_id'; // Specify the primary key
    public $incrementing = false; // Set to false if guardian_id is not an auto-incrementing integer
    protected $keyType = 'string'; // Set the key type if it's not an integer (e.g., if it's a string)
    protected $fillable =[
        'LRN',
        'regapproval_date',
        'payment_approval',
        'year_level',
        'guardian_name',
        'last_attended',
        'public_private',
        'date_register',
        'strand',
        'school_year'
    ];
    public function student()
    {   
        // return $this->hasMany(Student::class, 'guardian_id'); 
        return $this->belongsTo(Student::class, 'LRN', 'LRN');
    }
}
