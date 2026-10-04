<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use App\Models\Like;
use App\Models\Item;

class LikeController extends Controller
{
    public function add($id)
    {
        $item = Item::findOrFail($id);

        // すでにいいね済みなら何もしない（ON CONFLICT DO NOTHING）。同時に届いてもユニーク違反にならない
        Like::insertOrIgnore([
            'item_id' => $item->id,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['liked' => true, 'likes' => $item->likes()->count()]);
    }

    public function remove($id)
    {
        $item = Item::findOrFail($id);

        Like::where('item_id', $item->id)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['liked' => false, 'likes' => $item->likes()->count()]);
    }
}
