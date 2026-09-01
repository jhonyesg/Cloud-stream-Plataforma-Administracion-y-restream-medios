<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedNotification;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $validated, $request) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);

            AuditLog::record(
                'update.user.password',
                'user',
                $user->id,
                null,
                ['changed_by' => 'self', 'ip' => $request->ip()],
            );

            Mail::to($user)->queue(new PasswordChangedNotification(
                user: $user,
                changedBy: 'self',
                actor: null,
                ip: $request->ip(),
            ));

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();

            $user->setRememberToken(Str::random(60));
            $user->save();
        });

        $request->session()->regenerate();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status' => 'password-updated']);
        }

        return back()->with('status', 'password-updated');
    }
}
