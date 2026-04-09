<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->is_admin) {
            $conversations = Conversation::with(['user', 'lastMessage'])
                ->withCount(['unreadMessages as unread_count' => function ($query) {
                    $query->where('sender_type', 'user');
                }])
                ->orderByDesc(
                    Conversation::select('created_at')
                        ->from('messages')
                        ->whereColumn('messages.conversation_id', 'conversations.id')
                        ->latest()
                        ->take(1)
                )
                ->get();
        } else {
            $conversations = Conversation::where('user_id', $user->id)
                ->with('lastMessage')
                ->withCount(['unreadMessages as unread_count' => function ($query) {
                    $query->where('sender_type', 'admin');
                }])
                ->orderByDesc(
                    Conversation::select('created_at')
                        ->from('messages')
                        ->whereColumn('messages.conversation_id', 'conversations.id')
                        ->latest()
                        ->take(1)
                )
                ->get();
        }

        return response()->json($conversations);
    }



    public function indexs()
    {
        $user = auth()->user();

        // Déterminer quel type de message compter comme "non lu"
        $senderTypeToCount = $user->is_admin ? 'user' : 'admin';

        // Récupérer les conversations avec dernier message et unread_count
        $conversations = Conversation::with(['user', 'lastMessage'])
            ->withCount(['messages as unread_count' => function ($query) use ($senderTypeToCount, $user) {
                $query->where('is_read', false)
                    ->where('sender_type', $senderTypeToCount);

                if (!$user->is_admin) {
                    $query->where('conversation_id', '>', 0);
                }
            }])
            ->when(!$user->is_admin, function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderByDesc(
                Conversation::select('created_at')
                    ->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->latest()
                    ->take(1)
            )
            ->get();

        return response()->json($conversations);
    }
}
