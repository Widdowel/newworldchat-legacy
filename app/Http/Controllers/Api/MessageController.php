<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\WebSocketService;
use Illuminate\Http\Request;

class MessageController extends Controller
{


    protected $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }



    public function store(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'group_id' => 'nullable|exists:groups,id',
            'content' => 'nullable|string',
            'attachment' => 'nullable|file',
            'reply_to_message_id' => 'nullable|exists:messages,id'  // NOUVEAU
        ]);

        $user = auth()->user();

        if (
            !$user->is_admin &&
            (!isset($data['group_id']) || $data['group_id'] === null) &&
            (!isset($data['conversation_id']) || $data['conversation_id'] === null)
        ) {
            $conversation = Conversation::firstOrCreate(['user_id' => $user->id]);
            $data['conversation_id'] = $conversation->id;
        }

        if (isset($data['attachment'])) {
            $file = $data['attachment'];
            $originalExtension = $file->getClientOriginalExtension();
            $filename = time() . '_' . uniqid() . '.' . $originalExtension;
            $path = $file->storeAs('attachments', $filename, 'public');

            $data['attachment_path'] = $path;
            unset($data['attachment']);
        }

        $data['sender_type'] = $user->is_admin ? 'admin' : 'user';
        $data['user_id'] = $user->id;

        $message = Message::create($data);

        $message->load('replyToMessage.user');

        // $firebase = new FirebaseService();


        if (isset($data['conversation_id']) && $data['conversation_id'] != null) {
            if ($user->is_admin) {
                $conversation = Conversation::find($data['conversation_id']);
                $recipientId = $conversation->user_id;
                $recipient = User::find($conversation->user_id);
            } else {
                $recipientId = 1;
                $recipient = User::find(1);
            }


            // if ($recipient && $recipient->device_token) {
            //     $firebase->sendNotification(
            //         $recipient->device_token,
            //         'Nouveau message',
            //         $user->name . ': ' . substr($message->content ?? 'message', 0, 50),
            //         ['message_id' => $message->id, 'conversation_id' => $message->conversation_id]
            //     );
            // }



            $this->webSocketService->sendMessage(
                "global",
                $message->content ?? $message->attachment_path ?? 'message',
                $user->id,
                $message->user,
                $message->replyToMessage,
                $message->conversation_id,
                $message->group_id ?? null,
                $recipientId,
                $message->sender_type ?? 'user',
                // $message->replyToMessage,
            );

            $webSocketResult = $this->webSocketService->sendMessage(
                "room_" . $request->conversation_id,
                $message->content ?? $message->attachment_path ?? 'message',
                $user->id,
                $message->user,
                $message->replyToMessage,
                $message->conversation_id,
                $message->group_id ?? null,
                $recipientId,
                $message->sender_type ?? 'user',
                // $message->replyToMessage,
            );
        } else if (isset($data['group_id']) && $data['group_id'] != null) {
            $group = Group::find($data['group_id']);
            if (!$group) {
                return response()->json(['error' => 'Groupe non trouvé'], 404);
            }


            // $members = $group->members;
            // foreach ($members as $member) {
            //     if ($member->id !== $user->id && $member->device_token) {
            //         $firebase->sendNotification(
            //             $member->device_token,
            //             $group->name,
            //             $user->name . ': ' . substr($message->content ?? 'message', 0, 50),
            //             ['message_id' => $message->id, 'group_id' => $message->group_id]
            //         );
            //     }
            // }

            $recipientIds = $group->members->pluck('id')->toArray();

            $webSocketResult = $this->webSocketService->sendMessage(
                "group_" . $request->group_id,
                $message->content ?? $message->attachment_path ?? 'message',
                $user->id,
                $message->user,
                $message->replyToMessage,
                $message->conversation_id,
                $message->group_id ?? null,
                $recipientIds,
                $message->sender_type ?? 'user',
                // $message->replyToMessage,
            );

            $this->webSocketService->sendMessage(
                "global",
                $message->content ?? $message->attachment_path ?? 'message',
                $user->id,
                $message->user,
                $message->replyToMessagen,
                $message->conversation_id,
                $message->group_id ?? null,
                $recipientIds,
                $message->sender_type ?? 'user',
            );
        }

        return response()->json($message);
    }

    public function getGroupMessages($groupId)
    {
        $user = auth()->user();

        $unreadMessageIds = Message::where('group_id', $groupId)
            ->whereDoesntHave('readBy', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        if ($unreadMessageIds->isNotEmpty()) {
            $user->readMessages()->syncWithoutDetaching($unreadMessageIds);
        }

        $messages = Message::where('group_id', $groupId)
            ->with('group', 'user', 'replyToMessage.user')
            ->latest()
            ->get();

        return response()->json($messages);
    }


    public function getConversationMessages($conversationId)
    {
        $user = auth()->user();

        // Mettre à jour les messages non lus pour cet utilisateur
        Message::where('conversation_id', $conversationId)
            // ->where('sender_type', 'user') // ou 'admin' selon le rôle
            ->where('is_read', false)
            ->where('user_id', '!=', $user->id) // ne pas marquer les siens comme lus
            ->update(['is_read' => true]);

        // Récupérer les messages
        $messages = Message::where('conversation_id', $conversationId)
            ->with('conversation', 'replyToMessage.user')
            ->latest()
            ->paginate(30);

        return response()->json($messages);
    }


    public function destroy($id)
    {
        $user = auth()->user();

        if (!$user->is_admin) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }
        $message = Message::findOrFail($id);
        $message->delete();

        return response()->json(['success' => true, 'message' => 'Message supprimé']);
    }
}
