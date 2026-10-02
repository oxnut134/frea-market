<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'item_id',
        'item_name',
        'brand_name',
        'price',
        'description',
        'condition',
        'status',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    public function purchase()
    {
        //return $this->hasOne(Purchase::class, 'id', 'item_id' );
        return $this->hasOne(Purchase::class, 'item_id', 'id' );
    }
    public function category()
    {
        return $this->belongsToMany(Category::class, 'item_category', 'item_id', 'category_id');
    }

    public function likeToUser()
    {
        return $this->belongsToMany(User::class, 'likes', 'item_id', 'user_id');
    }

       // いいねのリレーションを追加
    public function likes()
    {
        return $this->hasMany(Like::class, 'item_id', 'id');
    }

    // 商品画像のURL（$item->image_url）
    public function getImageUrlAttribute()
    {
        return Storage::disk(config('filesystems.images'))->url($this->item_image);
    }

}
