<?php

namespace App\Services\Mail;

use App\Enums\MailCategory;

class MailFromResolver
{
    /**
     * @return array{address: string, name: string}
     */
    public function resolve(?string $category = null): array
    {
        $normalized = MailCategory::normalize($category);
        $configured = config("system_mail.{$normalized}", []);

        $address = trim((string) ($configured['address'] ?? ''));
        $name = trim((string) ($configured['name'] ?? ''));

        if ($address === '') {
            $address = trim((string) config('mail.from.address', ''));
        }

        if ($name === '') {
            $name = trim((string) config('mail.from.name', ''));
        }

        return [
            'address' => $address,
            'name' => $name,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function categoryAddressMap(): array
    {
        $map = [];
        foreach (MailCategory::all() as $category) {
            $sender = $this->resolve($category);
            $address = strtolower(trim((string) ($sender['address'] ?? '')));
            if ($address !== '') {
                $map[$category] = $address;
            }
        }

        return $map;
    }

    public function categoryForAddress(?string $address): string
    {
        $needle = strtolower(trim((string) $address));
        if ($needle === '') {
            return MailCategory::SYSTEM;
        }

        foreach ($this->categoryAddressMap() as $category => $mappedAddress) {
            if ($mappedAddress === $needle) {
                return $category;
            }
        }

        return MailCategory::SYSTEM;
    }
}

