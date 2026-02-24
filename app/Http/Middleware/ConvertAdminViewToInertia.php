<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvertAdminViewToInertia
{
    /**
     * @var array<string, array{component: string, title: string}>
     */
    private const PORTALS = [
        'admin.' => ['component' => 'Admin/Legacy/HtmlPage', 'title' => 'Admin'],
        'employee.' => ['component' => 'Employee/Legacy/HtmlPage', 'title' => 'Employee'],
        'client.' => ['component' => 'Client/Legacy/HtmlPage', 'title' => 'Client'],
        'rep.' => ['component' => 'Rep/Legacy/HtmlPage', 'title' => 'Sales'],
        'support.' => ['component' => 'Support/Legacy/HtmlPage', 'title' => 'Support'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $portal = $this->resolvePortal($request);
        if ($portal === null) {
            return $response;
        }

        if (! $request->isMethod('GET')) {
            return $response;
        }

        if (
            $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->boolean('partial')
            || $request->header('HX-Request')
            || $request->header('X-Inertia')
        ) {
            return $response;
        }

        if (
            $response instanceof JsonResponse
            || $response instanceof RedirectResponse
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
        ) {
            return $response;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $content = (string) $response->getContent();
        if ($content === '') {
            return $response;
        }

        // Already an Inertia response payload.
        if (str_contains($content, 'data-page=')) {
            return $response;
        }

        $payload = $this->extractPayload($content);
        if ($payload === null) {
            return $response;
        }

        $inertiaResponse = Inertia::render($portal['component'], [
            'pageTitle' => $payload['page_title'] ?: ($portal['title'] . ' - ' . config('app.name', 'MyApptimatic')),
            'pageHeading' => $payload['page_heading'] ?: 'Overview',
            'pageKey' => $payload['page_key'] ?: ($request->route()?->getName() ?? ''),
            'content_html' => $payload['content_html'],
            'script_html' => $payload['script_html'],
            'style_html' => $payload['style_html'],
        ])->toResponse($request);

        $inertiaResponse->setStatusCode($response->getStatusCode());

        return $inertiaResponse;
    }

    /**
     * @return array{component: string, title: string}|null
     */
    private function resolvePortal(Request $request): ?array
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName === '') {
            return null;
        }

        foreach (self::PORTALS as $prefix => $portal) {
            if (str_starts_with($routeName, $prefix)) {
                return $portal;
            }
        }

        return null;
    }

    private function extractPayload(string $html): ?array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (! $loaded) {
            return null;
        }

        $contentNode = $dom->getElementById('appContent');
        if (! $contentNode) {
            return null;
        }

        $scriptsNode = $dom->getElementById('pageScriptStack');

        $styleHtml = '';
        foreach ($dom->getElementsByTagName('style') as $styleNode) {
            $styleHtml .= $dom->saveHTML($styleNode);
        }

        return [
            'page_title' => (string) $contentNode->getAttribute('data-page-title'),
            'page_heading' => (string) $contentNode->getAttribute('data-page-heading'),
            'page_key' => (string) $contentNode->getAttribute('data-page-key'),
            'content_html' => $this->innerHtml($contentNode),
            'script_html' => $scriptsNode ? $this->innerHtml($scriptsNode) : '',
            'style_html' => $styleHtml,
        ];
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
