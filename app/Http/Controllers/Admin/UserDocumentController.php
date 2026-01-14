<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class UserDocumentController extends Controller
{
    public function show(Request $request, string $type, int $id, string $doc)
    {
        $actor = $request->user() ?? Auth::guard('support')->user();

        if (! $actor) {
            abort(403);
        }

        Gate::forUser($actor)->authorize('view-documents');

        $doc = strtolower($doc);
        $mapping = $this->documentMapping();

        if (! isset($mapping[$type])) {
            abort(404);
        }

        $docField = $mapping[$type]['fields'][$doc] ?? null;
        if (! $docField) {
            abort(404);
        }

        $model = $mapping[$type]['model']::findOrFail($id);
        $path = $model->{$docField} ?? null;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $fileName = basename($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    private function documentMapping(): array
    {
        return [
            'employee' => [
                'model' => Employee::class,
                'fields' => [
                    'nid' => 'nid_path',
                    'cv' => 'cv_path',
                ],
            ],
            'customer' => [
                'model' => Customer::class,
                'fields' => [
                    'nid' => 'nid_path',
                    'cv' => 'cv_path',
                ],
            ],
            'sales-rep' => [
                'model' => SalesRepresentative::class,
                'fields' => [
                    'nid' => 'nid_path',
                    'cv' => 'cv_path',
                ],
            ],
            'user' => [
                'model' => User::class,
                'fields' => [
                    'nid' => 'nid_path',
                    'cv' => 'cv_path',
                ],
            ],
        ];
    }
}
