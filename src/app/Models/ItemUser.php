<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemUser extends Model
{
    use HasFactory;

    protected $table = 'item_user'; 
        protected $fillable = [
        'item_id',
        'user_id',
    ];


}
