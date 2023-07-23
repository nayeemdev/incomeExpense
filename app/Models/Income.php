<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Income extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'income_date' => 'date'
    ];

    public function User()
    {
    	return $this->hasOne('App\Models\User', 'user_id', 'id');
    }
}
