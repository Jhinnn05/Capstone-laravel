<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class ParentGuardian extends Model
{
    /** @use HasFactory<\Database\Factories\ParentGuardianFactory> */
    use HasFactory,HasApiTokens;
    // protected $primaryKey = 'guardian_id';
    protected $table = 'parent_guardians';
    protected $primaryKey = 'guardian_id'; // Specify the primary key
    public $incrementing = false; // Set to false if guardian_id is not an auto-incrementing integer
    protected $keyType = 'string'; // Set the key type if it's not an integer (e.g., if it's a string)
    protected $fillable =[
        'LRN',
        'fname',
        'lname',
        'mname',
        'address',
        'relationship',
        'contact_no',
        'email',
        'password',
        'parent_pic'
    ];
    public function student()
    {   
        // return $this->hasMany(Student::class, 'guardian_id'); 
        return $this->belongsTo(Student::class, 'LRN', 'LRN');
    }
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function students()
    {
        return $this->hasMany(Student::class, 'LRN', 'LRN');
    }
}
