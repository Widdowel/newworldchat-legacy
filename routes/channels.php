<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin', function ($user) {
    return true ; //$user->is_admin === 1; // seul Émile (admin) peut écouter
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    // vérifier que l'utilisateur appartient à la conversation ou est admin
    $conversation = Conversation::find($id);
    if (!$conversation) return false;
    return $user->is_admin || $user->id === (int) $conversation->user_id;
});
