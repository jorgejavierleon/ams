<?php

namespace App\Http\Controllers\Dt;

use App\Http\Controllers\Controller;
use App\Mail\SendDtPassword;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/dt-forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'regex:/^.+@dt\.gov\.cl$/'],
        ], [
            'email.regex' => 'El correo debe ser de dominio @dt.gov.cl',
        ]);

        $password = Str::password(12, true, true, false);
        $user = $this->upsertDtUser($request->email, $password);

        Mail::to($user)->queue(new SendDtPassword($password));

        return back()->with('status', 'Se ha enviado un correo con la clave solicitada.');
    }

    private function upsertDtUser(string $email, string $password): User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->where('is_dt', true)->first();

        if ($user) {
            $user->update([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
            ]);

            return $user;
        }

        return User::create([
            'name' => 'Usuario DT',
            'email' => $email,
            'is_dt' => true,
            'password' => Hash::make($password),
            'password_changed_at' => now(),
            'email_verified_at' => now(),
        ]);
    }
}
