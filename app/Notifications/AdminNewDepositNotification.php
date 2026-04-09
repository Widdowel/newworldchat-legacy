<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewDepositNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $user, public $amount, public $network, public $address) {}

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Nouvelle demande de dépôt")
            ->greeting("Nouvelle demande de dépôt")
            ->line("Utilisateur: {$this->user->name} ({$this->user->email})")
            ->line("Montant: {$this->amount} {$this->network}")
            ->line("TRX: {$this->address}")
            ->line("Merci de vérifier le dépôt.");
    }
}

