<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'pendingEmail' => $request->session()->get('auth_pending_email'),
            'devOtpHint' => app()->environment('local') ? $request->session()->get('auth_dev_otp') : null,
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        $throttleKey = 'otp-send:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Terlalu banyak permintaan kode. Silakan coba lagi dalam {$seconds} detik.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $user = User::whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'Email tidak terdaftar atau akun sedang dinonaktifkan.',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'provisit_login_otp:'.sha1($email);

        Cache::put($cacheKey, [
            'hash' => Hash::make($otp),
            'user_id' => $user->id,
            'attempts' => 0,
        ], now()->addMinutes(10));

        Log::info("PROvisit Login OTP untuk {$email}: {$otp}");

        try {
            Mail::raw(
                "Kode verifikasi login PROvisit Anda adalah: {$otp}\n\nKode berlaku selama 10 menit. Jangan bagikan kode ini kepada siapa pun.",
                function ($message) use ($email): void {
                    $message->to($email)->subject('Kode Login PROvisit - PT Mitra Analitika');
                }
            );
        } catch (Throwable $e) {
            Log::warning("Gagal mengirim email OTP ke {$email}: ".$e->getMessage());
        }

        $redirect = back()
            ->with('auth_pending_email', $email)
            ->with('success', "Kode verifikasi 6-digit telah dikirim ke {$email}.");

        if (app()->environment('local')) {
            $redirect->with('auth_dev_otp', $otp);
        }

        return $redirect;
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($validated['email']));
        $cacheKey = 'provisit_login_otp:'.sha1($email);
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || empty($payload['hash']) || empty($payload['user_id'])) {
            return back()
                ->with('auth_pending_email', $email)
                ->withErrors(['code' => 'Kode verifikasi sudah kedaluwarsa. Silakan minta kode baru.']);
        }

        if (($payload['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);

            return back()->withErrors([
                'email' => 'Terlalu banyak percobaan salah. Silakan minta kode verifikasi baru.',
            ]);
        }

        if (! Hash::check($validated['code'], $payload['hash'])) {
            $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $payload, now()->addMinutes(10));

            return back()
                ->with('auth_pending_email', $email)
                ->withErrors(['code' => 'Kode verifikasi tidak sesuai.']);
        }

        $user = User::where('id', $payload['user_id'])
            ->where('is_active', true)
            ->first();

        Cache::forget($cacheKey);

        if (! $user) {
            return back()->withErrors([
                'email' => 'Akun pengguna tidak ditemukan atau tidak aktif.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('visits.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
