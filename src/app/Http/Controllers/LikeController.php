<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use App\Models\Like;
use App\Models\Item;

class LikeController extends Controller
{
    public function add($id)
    {
        $current_user_id = Auth::id(); // 本番ではAuth::id()を使用
        $item = Item::findOrFail($id);

        $like = Like::firstOrCreate([
            'item_id' => $id,
            'user_id' => $current_user_id,
        ]);

        if ($like->wasRecentlyCreated) {
            $item->increment('likes_count');
        }

        return response()->json(['likes' => $item->likes_count]);
    }

    public function remove($id)
    {
        $current_user_id = Auth::id(); // 本番ではAuth::id()を使用
        $item = Item::findOrFail($id);

        $deleted = Like::where('item_id', $id)
            ->where('user_id', $current_user_id)
            ->delete();

        if ($deleted) {
            $item->decrement('likes_count');
        }

        return response()->json(['likes' => $item->likes_count]);
    }
}
