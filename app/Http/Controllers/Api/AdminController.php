<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function conversations()
    {
        return Conversation::with('user')->latest()->get();
    }

    public function conversationMessages($id)
    {
        return Conversation::with('messages')->findOrFail($id);
    }

    public function index()
    {
        $users = User::all();

        return response()->json([
            'users' => $users
        ]);
    }
}
