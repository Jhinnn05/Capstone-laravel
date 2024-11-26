<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Student as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model
{
    use HasFactory, Notifiable, HasApiTokens;
    // protected $primaryKey = 'LRN';
    protected $table = 'students';
    protected $fillable =[
        'LRN',
        'fname',
        'lname',
        'mname',
        'suffix',
        'bdate',
        'bplace',
        'gender',
        'religion',
        'address',
        'contact_no',
        'email',
        'password'
    ];
    

    public function parentGuardians()
    {
        return $this->hasMany(ParentGuardian::class, 'LRN', 'LRN');
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
}
