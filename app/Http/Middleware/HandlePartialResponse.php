<?php

namespace App\Http\Middleware;

use Closure;
use DOMDocument;
use DOMNode;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HandlePartialResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldReturnPartial($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        $payload = $this->extractPartialPayload($content, $request->route()?->getName());
        if ($payload === null) {
            return $response;
        }

        $response->setContent($payload['html']);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('X-Partial-Response', 'true');

        if ($payload['title'] !== '') {
            $response->headers->set('X-Page-Title', $payload['title']);
        }

        if ($payload['page_key'] !== '') {
            $response->headers->set('X-Page-Key', $payload['page_key']);
        }

        $this->appendVaryHeaders($response, ['X-Partial', 'X-Requested-With']);

        return $response;
    }

    private function shouldReturnPartial(Request $request, mixed $response): bool
    {
        if (
            ! $response instanceof Response
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
        ) {
            return false;
        }

        if ($request->method() !== 'GET') {
            return false;
        }

        if ($response->isRedirection()) {
            return false;
        }

        $xPartial = $request->headers->get('X-Partial');
        $isPartial = filter_var($xPartial, FILTER_VALIDATE_BOOLEAN) || $request->ajax();
        if (! $isPartial) {
            return false;
        }

        $acceptHeader = strtolower((string) $request->headers->get('Accept', ''));
        if (
            $acceptHeader === ''
            || (! str_contains($acceptHeader, 'text/html') && ! str_contains($acceptHeader, '*/*'))
        ) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains(strtolower($contentType), 'text/html');
    }

    /**
     * @return array{html: string, title: string, page_key: string}|null
     */
    private function extractPartialPayload(string $html, ?string $routeName): ?array
    {
        if (! str_contains($html, 'id="appContent"') && ! str_contains($html, "id='appContent'")) {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        try {
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $encodedHtml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }

        if (! $loaded) {
            return null;
        }

        $contentNode = $dom->getElementById('appContent');
        if ($contentNode === null) {
            return null;
        }

        $contentHtml = $this->innerHtml($dom, $contentNode);
        if (trim($contentHtml) === '') {
            return null;
        }

        $pageScripts = '';
        $pageScriptStack = $dom->getElementById('pageScriptStack');
        if ($pageScriptStack !== null) {
            $pageScripts = trim($this->innerHtml($dom, $pageScriptStack));
        }

        if ($pageScripts !== '') {
            $contentHtml .= "\n<div data-partial-scripts hidden>\n{$pageScripts}\n</div>";
        }

        $title = trim((string) $contentNode->getAttribute('data-page-title'));
        if ($title === '') {
            $titleNode = $dom->getElementsByTagName('title')->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';
        }

        $pageKey = trim((string) $contentNode->getAttribute('data-page-key'));
        if ($pageKey === '' && $routeName !== null) {
            $pageKey = $routeName;
        }

        return [
            'html' => $contentHtml,
            'title' => $title,
            'page_key' => $pageKey,
        ];
    }

    private function innerHtml(DOMDocument $dom, DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= $dom->saveHTML($childNode);
        }

        return $html;
    }

    private function appendVaryHeaders(Response $response, array $headers): void
    {
        $existing = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary', ''))));

        foreach ($headers as $header) {
            if (! in_array($header, $existing, true)) {
                $existing[] = $header;
            }
        }

        if ($existing !== []) {
            $response->headers->set('Vary', implode(', ', $existing));
        }
    }
}
