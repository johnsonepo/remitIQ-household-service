<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    /**
     * Determine which channels the notification should use.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the verification URL for the frontend.
     *
     * The frontend receives the user ID and verification hash and then
     * submits those values to the API's email verification endpoint.
     */
    protected function verificationUrl(object $notifiable): string
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $hash = sha1($notifiable->getEmailForVerification());

        return "{$frontendUrl}/verify-email/{$notifiable->getKey()}/{$hash}";
    }

    /**
     * Build the verification email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $url)
            ->line('If you did not create an account, no further action is required.');
    }
}
