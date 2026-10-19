<?php

namespace App\Http\Controllers\AuthFresh;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthFresh\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Moves a client who is also a sales rep between the client and sales portals
 * without a second login: the account is already authenticated on one guard,
 * so it is signed in to the other guard as the same user.
 */
class PortalSwitchController extends Controller
{
    public function __invoke(Request $request, string $target): RedirectResponse
    {
        return match ($target) {
            'sales' => $this->toSales($request),
            'client' => $this->toClient($request),
            default => abort(404),
        };
    }

    private function toSales(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $user->canUseSalesPortalAsClient()) {
            abort(403);
        }

        if ((int) Auth::guard('sales')->id() !== (int) $user->id) {
            Auth::guard('sales')->login($user);
        }

        Portal::setPortal($request, 'sales');

        return redirect()->route('rep.dashboard');
    }

    private function toClient(Request $request): RedirectResponse
    {
        $user = Auth::guard('sales')->user();

        if (! $user instanceof User || $user->role !== Role::CLIENT) {
            abort(403);
        }

        if ((int) Auth::guard('web')->id() !== (int) $user->id) {
            Auth::guard('web')->login($user);
        }

        Portal::setPortal($request, 'web');

        return redirect()->route('client.dashboard');
    }
}
