<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect($this->appUrl('/auth/verify-email?error=invalid'));
        }

        $user = User::findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect($this->appUrl('/auth/verify-email?error=invalid'));
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect($this->appUrl('/onboarding'));
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        try {
            Mail::to($user)->send(new VerifyEmailMail($user));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function sendForUser(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        Mail::to($user)->send(new VerifyEmailMail($user));
    }

    private function appUrl(string $path): string
    {
        return rtrim(config('locknear.app_url'), '/') . $path;
    }
}
