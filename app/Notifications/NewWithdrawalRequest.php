<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewWithdrawalRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public $user, $amount, $token, $network, $receiver;

    public function __construct($user, $amount, $token, $network, $receiver)
    {
        $this->user = $user;
        $this->amount = $amount;
        $this->token = $token;
        $this->network = $network;
        $this->receiver = $receiver;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Nouvelle demande de retrait")
            ->greeting("Salut Admin,")
            ->line("Une nouvelle demande de retrait vient d’être effectuée.")
            ->line("Utilisateur : {$this->user->name} ({$this->user->email})")
            ->line("Montant : {$this->amount} {$this->token}")
            ->line("Réseau : {$this->network}")
            ->line("Adresse : {$this->receiver}")
            ->line("Merci de traiter cette demande rapidement.");
    }
}
