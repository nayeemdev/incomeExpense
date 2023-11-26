<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Note extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'note_date' => 'datetime'
    ];

    public function User()
    {
    	return $this->hasOne('App\Models\User', 'user_id', 'id');
    }
}
