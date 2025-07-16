<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Http\Requests\CommentRequest;


class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'comment',
    ];

    public static function addComment(Request $request)
    {
        //dd($request);
        $item_id = $request->item_id;
        $user_id = 1;                     //1は本番ではAuth::id()となる

        $comment = new Comment;
        $comment->item_id = $item_id;
        $comment->user_id = $user_id;
        $comment->comment = $request->comment;
        $comment->save();

        return;
    }
}
