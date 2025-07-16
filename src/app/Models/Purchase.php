<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'payment_method',
        'delivery_address',
    ];

    /*public function itemManyToMany()
    {
        return $this->belongsToMany(Item::class, 'purchase_item', 'purchase_id', 'item_id');
    }*/
    public function item()
    {
        return $this->belongsTo(Item::class, 'id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
