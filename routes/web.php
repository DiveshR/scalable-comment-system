<?php

use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $posts = Post::visible()
        ->whereHas('user', function($query) {
            $query->where('is_admin', false);
        })
        ->with('user')
        ->latest()
        ->paginate(10);
        
    return view('welcome', compact('posts'));
});
