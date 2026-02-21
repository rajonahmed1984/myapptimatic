<?php

namespace App\Mail\Concerns;

use App\Enums\MailCategory;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

trait UsesMailCategory
{
    public function mailCategory(): string
    {
        return MailCategory::SYSTEM;
    }

    protected function withMailCategoryHeader(MailMessage $message): MailMessage
    {
        return $message->withSymfonyMessage(function (Email $email) {
            $headers = $email->getHeaders();
            $headerName = 'X-Apptimatic-Mail-Category';
            if ($headers->has($headerName)) {
                $headers->remove($headerName);
            }
            $headers->addTextHeader($headerName, MailCategory::normalize($this->mailCategory()));
        });
    }

    protected function withMailableCategoryHeader(): static
    {
        return $this->withSymfonyMessage(function (Email $email) {
            $headers = $email->getHeaders();
            $headerName = 'X-Apptimatic-Mail-Category';
            if ($headers->has($headerName)) {
                $headers->remove($headerName);
            }
            $headers->addTextHeader($headerName, MailCategory::normalize($this->mailCategory()));
        });
    }
}

