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
        array $extra = []
    ): JsonResponse {
        return response()->json(array_merge([
            'ok' => true,
            'message' => $message,
            'closeModal' => $closeModal,
            'patches' => $patches,
            'redirect' => $redirect,
        ], $extra));
    }

    public static function ajaxValidation(
        array $errors,
        ?string $formHtml = null,
        string $message = 'Validation failed'
    ): JsonResponse {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'errors' => $errors,
            'formHtml' => $formHtml,
        ], 422);
    }
}
