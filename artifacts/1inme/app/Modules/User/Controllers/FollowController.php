<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function followers(Request $request)
    {
        $me = auth()->user();
        $followers = Follow::with('follower')
            ->where('creator_id', $me->id)
            ->orderByDesc('created_at')
            ->paginate(30);
        return view('user.followers.index', compact('followers'));
    }

    public function following(Request $request)
    {
        $me = auth()->user();
        $following = Follow::with('creator')
            ->where('follower_id', $me->id)
            ->orderByDesc('created_at')
            ->paginate(30);
        return view('user.followers.following', compact('following'));
    }
}
