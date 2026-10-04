<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use App\Models\Like;
use App\Models\Item;

class LikeController extends Controller
{
    public function add($id)
    {
        $current_user_id = Auth::id(); // 本番ではAuth::id()を使用
        $item = Item::findOrFail($id);

        Like::firstOrCreate([
            'item_id' => $id,
            'user_id' => $current_user_id,
        ]);

        return response()->json(['likes' => $item->likes()->count()]);
    }

    public function remove($id)
    {
        $current_user_id = Auth::id(); // 本番ではAuth::id()を使用
        $item = Item::findOrFail($id);

        Like::where('item_id', $id)
            ->where('user_id', $current_user_id)
            ->delete();

        return response()->json(['likes' => $item->likes()->count()]);
    }
}
