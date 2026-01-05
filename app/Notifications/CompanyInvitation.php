<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private string $token)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $registrationUrl = "{$frontendUrl}/register?token={$this->token}";
        $expiryDays = config('app.invitation_expiry_days', 7);

        return (new MailMessage)
            ->subject('Invitation to Join Academic Finder')
            ->greeting('Hello!')
            ->line('You have been invited to join Academic Finder as a company partner.')
            ->line('Click the button below to complete your registration:')
            ->action('Complete Registration', $registrationUrl)
            ->line("This invitation will expire in {$expiryDays} days.")
            ->line('If you did not expect this invitation, please ignore this email.')
            ->salutation('Best regards, Academic Finder Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
