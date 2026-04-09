<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://getme.leedixpay.com/api';
    }

    /**
     * Envoyer un message via WebSocket
     */
    public function sendMessage($roomId, $message, $senderId, $user, $replymessage, $conversationId = null, $groupId = null, $recipientId = null, $messageType = 'text')
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/send-message", [
                'roomId' => $roomId,
                'message' => $message,
                'senderId' => $senderId,
                'recipientId' => $recipientId,
                'messageType' => $messageType,
                'user' => $user,
                'conversation_id' => intval($conversationId) ?? null,
                'group_id' => intval($groupId) ?? null,
                'reply_to_message' => $replymessage,


            ]);

            if ($response->successful()) {
                Log::info('Message WebSocket envoyé avec succès', [
                    'roomId' => $roomId,
                    'senderId' => $senderId
                ]);
                return $response->json();
            }

            Log::error('Erreur lors de l\'envoi du message WebSocket', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'envoi du message WebSocket: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour le statut d'un utilisateur
     */
    public function updateUserStatus($userId, $status, $roomId = null)
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/user-status", [
                'userId' => $userId,
                'status' => $status,
                'roomId' => $roomId
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception lors de la mise à jour du statut: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification push
     */
    public function sendNotification($userId, $title, $body, $data = [])
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/notification", [
                'userId' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => $data
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'envoi de notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir le statut du serveur WebSocket
     */
    public function getServerStatus()
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/status");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération du statut: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les utilisateurs connectés d'une room
     */
    public function getRoomUsers($roomId)
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/room/{$roomId}/users");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des utilisateurs de la room: ' . $e->getMessage());
            return null;
        }
    }
}
