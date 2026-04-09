<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseService
{
    private $messaging;

    public function __construct()
    {
        $factory = new Factory();
        $firebase = $factory->withServiceAccount(storage_path(env('FIREBASE_CREDENTIALS')));
        $this->messaging = $firebase->createMessaging();
    }

    public function sendNotification($deviceToken, $title, $body, $data = [])
    {
        $message = CloudMessage::withTarget('token', $deviceToken)
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ])
            ->withData($data);

        return $this->messaging->send($message);
    }

    public function sendToMultiple($deviceTokens, $title, $body, $data = [])
    {
        $message = CloudMessage::new()
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ])
            ->withData($data);

        return $this->messaging->sendMulticast($message, $deviceTokens);
    }
}