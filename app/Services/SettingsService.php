<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_TTL = 300;

    public function recaptchaEnabled(): bool
    {
        return (bool) $this->getCached('recaptcha_enabled', config('recaptcha.enabled'));
    }

    public function recaptchaSiteKey(): ?string
    {
        $value = $this->getCached('recaptcha_site_key', config('recaptcha.site_key'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function recaptchaSecretKey(): ?string
    {
        $value = $this->getCached('recaptcha_secret_key', config('recaptcha.secret_key'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function recaptchaProjectId(): ?string
    {
        $value = $this->getCached('recaptcha_project_id', config('recaptcha.project_id'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function recaptchaApiKey(): ?string
    {
        $value = $this->getCached('recaptcha_api_key', config('recaptcha.api_key'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function recaptchaScoreThreshold(): float
    {
        $value = $this->getCached('recaptcha_score_threshold', config('recaptcha.score_threshold'));

        return is_numeric($value) ? (float) $value : 0.5;
    }

    public function recaptchaConfig(): array
    {
        return [
            'recaptcha.enabled' => $this->recaptchaEnabled(),
            'recaptcha.site_key' => $this->recaptchaSiteKey(),
            'recaptcha.secret_key' => $this->recaptchaSecretKey(),
            'recaptcha.project_id' => $this->recaptchaProjectId(),
            'recaptcha.api_key' => $this->recaptchaApiKey(),
            'recaptcha.score_threshold' => $this->recaptchaScoreThreshold(),
        ];
    }

    private function getCached(string $key, mixed $default = null): mixed
    {
        return Cache::remember('settings.' . $key, self::CACHE_TTL, function () use ($key, $default) {
            return Setting::getValue($key, $default);
        });
    }
}
