<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use App\Services\Odoo\OdooAuthService;
use App\Services\Odoo\OdooUserSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        OdooAuthService $odooAuthService,
        OdooUserSynchronizer $odooUserSynchronizer
    ): RedirectResponse
    {
        $request->authenticate($odooAuthService, $odooUserSynchronizer);

        $request->session()->regenerate();

        return redirect()->intended($this->defaultRedirectPath($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function defaultRedirectPath(?User $user): string
    {
        if ($user?->isManagerLike()) {
            return route('manager.dashboard');
        }

        return RouteServiceProvider::HOME;
    }
}
