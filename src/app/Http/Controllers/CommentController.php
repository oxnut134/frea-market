<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Http\Requests\CommentRequest;

class CommentController extends Controller
{
    public function addComment(CommentRequest $request)
    {
        //dd('break');
        Comment::addComment($request);

        //return view('detail', $request['id']);
        //return redirect()->route('detail', ['id' => $request['id']]);
        return back();
    }
}
