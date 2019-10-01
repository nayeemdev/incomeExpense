<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = [];

    public function User()
    {
    	return $this->hasOne('App\Models\User', 'user_id', 'id');
    }
}
