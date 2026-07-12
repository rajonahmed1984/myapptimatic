<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $companyName = (string) Setting::getValue('company_name', config('app.name'));
        $subject = 'Reset your '.$companyName.' password';

        return (new MailMessage())
            ->subject($subject)
            ->view('emails.password-reset', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => Branding::url(Setting::getValue('company_logo_path')),
                'portalUrl' => UrlResolver::portalUrl(),
                'resetUrl' => $this->resetUrl($notifiable),
                'userName' => (string) ($notifiable->name ?: $notifiable->email),
                'expiresInMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
