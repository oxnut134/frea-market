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
    ];

    const SALE_STATUS_ON_SALE = 'on_sale';
    const SALE_STATUS_TRADING = 'trading';
    const SALE_STATUS_SOLD = 'sold';

    // 購入の全履歴（期限切れ・失敗した試みも含む）
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'item_id', 'id');
    }

    // 有効な購入（支払い済み、または期限内の確保中）。1 つの商品に 1 件だけ
    public function activePurchase()
    {
        return $this->hasOne(Purchase::class, 'item_id', 'id')->active();
    }

    // 販売状況（$item->sale_status）：on_sale / trading / sold
    public function getSaleStatusAttribute()
    {
        $purchase = $this->activePurchase;
        if (!$purchase) {
            return self::SALE_STATUS_ON_SALE;
        }

        return $purchase->status === Purchase::STATUS_PAID ? self::SALE_STATUS_SOLD : self::SALE_STATUS_TRADING;
    }

    // 販売状況の表示（$item->sale_status_label）：販売中は空
    public function getSaleStatusLabelAttribute()
    {
        switch ($this->sale_status) {
            case self::SALE_STATUS_SOLD:
                return 'SOLD';
            case self::SALE_STATUS_TRADING:
                return '取引中';
            default:
                return '';
        }
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
