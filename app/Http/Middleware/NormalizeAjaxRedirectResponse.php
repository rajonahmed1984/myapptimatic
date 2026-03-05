<?php

namespace App\Http\Middleware;

use App\Support\AjaxResponse;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

class NormalizeAjaxRedirectResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldNormalize($request, $response)) {
            return $response;
        }

        /** @var RedirectResponse $response */
        $targetUrl = $response->getTargetUrl();
        $session = $request->hasSession() ? $request->session() : null;
        $targetPath = (string) parse_url($targetUrl, PHP_URL_PATH);
        $normalizedTargetPath = '/' . ltrim($targetPath, '/');

        $errors = $session?->get('errors');
        $defaultErrorMessages = $errors instanceof ViewErrorBag
            ? $errors->getBag('default')->getMessages()
            : [];

        if ($defaultErrorMessages !== []) {
            return AjaxResponse::ajaxValidation(
                $defaultErrorMessages,
                null,
                (string) ($session?->get('error') ?? 'Validation failed')
            );
        }

        $errorMessage = (string) ($session?->get('error') ?? '');
        if ($errorMessage !== '') {
            return AjaxResponse::ajaxError($errorMessage, 422, redirect: $targetUrl);
        }

        if (preg_match('#/(admin|employee|sales|support)?/?login$#i', $normalizedTargetPath)) {
            return AjaxResponse::ajaxError('Authentication required.', 401, redirect: $targetUrl);
        }

        $message = trim((string) ($session?->get('status') ?? $session?->get('success') ?? $session?->get('message') ?? ''));

        return AjaxResponse::ajaxRedirect(
            $targetUrl,
            $message !== '' ? $message : 'Completed.',
            false
        );
    }

    private function shouldNormalize(Request $request, mixed $response): bool
    {
        if (! $response instanceof RedirectResponse) {
            return false;
        }

        // Inertia expects redirect/validation semantics, not JSON ajax envelopes.
        if ($request->header('X-Inertia')) {
            return false;
        }

        if ($request->isMethod('GET')) {
            return false;
        }

        if (! AjaxResponse::ajaxFromRequest($request)) {
            return false;
        }

        return true;
    }
}
