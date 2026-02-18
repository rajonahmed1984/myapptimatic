<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AjaxResponse
{
    public static function ajaxFromRequest(Request $request): bool
    {
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return true;
        }

        $header = strtolower((string) $request->header('X-Ajax-Action', ''));
        if (in_array($header, ['1', 'true', 'yes'], true)) {
            return true;
        }

        return false;
    }

    public static function ajaxOk(
        string $message,
        array $patches = [],
        ?string $redirect = null,
        bool $closeModal = true,
        ?array $replace = null,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge(self::basePayload(
            ok: true,
            status: 'success',
            message: $message,
            patches: $patches,
            redirect: $redirect,
            closeModal: $closeModal,
            replace: $replace,
        ), $extra));
    }

    public static function ajaxRedirect(
        string $redirect,
        string $message = 'Done.',
        bool $closeModal = false,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge(self::basePayload(
            ok: true,
            status: 'success',
            message: $message,
            patches: [],
            redirect: $redirect,
            closeModal: $closeModal,
            replace: null,
        ), $extra));
    }

    public static function ajaxError(
        string $message,
        int $statusCode = 422,
        array $errors = [],
        ?string $redirect = null,
        ?array $replace = null,
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge(self::basePayload(
            ok: false,
            status: 'error',
            message: $message,
            patches: [],
            redirect: $redirect,
            closeModal: false,
            replace: $replace,
            errors: $errors,
        ), $extra), $statusCode);
    }

    public static function ajaxValidation(
        array $errors,
        ?string $formHtml = null,
        string $message = 'Validation failed'
    ): JsonResponse {
        return response()->json(self::basePayload(
            ok: false,
            status: 'error',
            message: $message,
            patches: [],
            redirect: null,
            closeModal: false,
            replace: null,
            errors: $errors,
            formHtml: $formHtml,
        ), 422);
    }

    private static function basePayload(
        bool $ok,
        string $status,
        string $message,
        array $patches = [],
        ?string $redirect = null,
        bool $closeModal = false,
        ?array $replace = null,
        array $errors = [],
        ?string $formHtml = null
    ): array {
        return [
            'ok' => $ok,
            'status' => $status,
            'message' => $message,
            'redirect' => $redirect,
            'replace' => $replace,
            'closeModal' => $closeModal,
            'patches' => $patches,
            'errors' => $errors,
            'formHtml' => $formHtml,
        ];
    }
}
