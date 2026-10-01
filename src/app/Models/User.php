<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    use Billable;
    use Notifiable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /*protected $casts = [
        'email_verified_at' => 'datetime',
    ];*/

    public function likeToItem()
    {
        return $this->belongsToMany(Item::class, 'likes', 'item_id', 'user_id');
    }

    public function commentToItem()
    {
        return $this->belongsToMany(Item::class, 'comments', 'item_id', 'user_id');
    }

    /*public function itemsManyT0Many()
    {
        return $this->belongsToMany(Item::class, 'item_user', 'user_id', 'item_id');
    }*/
    public function items()
    {
        return $this->hasMany(Item::class, 'id', 'user_id');
    }
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }
}
