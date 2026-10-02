<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_image',
        'post_code',
        'address',
        'building',
    ];

    // プロフィール画像のURL（$profile->image_url）。未設定の場合はデフォルトのアイコン
    public function getImageUrlAttribute()
    {
        if (empty($this->profile_image)) {
            return asset('images/default-profile.svg');
        }

        return Storage::disk(config('filesystems.images'))->url($this->profile_image);
    }
}
