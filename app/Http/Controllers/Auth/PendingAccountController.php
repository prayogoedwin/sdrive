<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PendingAccountController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isActive()) {
            return redirect()->route('drive');
        }

        return view('auth.pending', [
            'user' => $request->user(),
        ]);
    }
}
