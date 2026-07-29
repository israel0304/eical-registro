<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BienvenidaNuevoUsuario extends Notification
{
    use Queueable;

    public function __construct(
        public string $activationUrl,
        public string $userName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido a Registro EICAL 2026')
            ->greeting('¡Hola '.$this->userName.'!')
            ->line('Has sido registrado en el sistema de registro del Encuentro de Innovación, Ciencia, Tecnología, Academia y Saberes (EICAL) 2026.')
            ->line('Para activar tu cuenta y establecer tu contraseña, haz clic en el siguiente enlace:')
            ->action('Activar mi cuenta', $this->activationUrl)
            ->line('Este enlace expirará en 60 minutos.')
            ->line('Si no esperabas este correo, puedes ignorarlo.')
            ->salutation('Saludos, el equipo de Registro EICAL');
    }
}
