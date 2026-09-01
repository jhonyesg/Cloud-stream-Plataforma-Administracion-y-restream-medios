<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectController extends Controller
{
    public function home(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'client.dashboard');
        }

        return view('landing');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'client.dashboard');
    }
}
