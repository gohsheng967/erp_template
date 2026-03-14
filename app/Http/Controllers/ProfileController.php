<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'bankOptions' => config('banks.malaysia', []),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $publicDisk = Storage::disk('public');

        if (!$publicDisk->exists('signatures')) {
            $publicDisk->makeDirectory('signatures');
        }

        $user->fill([
            'name' => $request->input('name'),
            'email' => strtolower((string) $request->input('email')),
        ]);

        // Save contact channels
        $channels = $request->contact_channels;

        if (is_string($channels)) {
            $channels = json_decode($channels, true);
        }

        $user->contact_channels = $channels;

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo = $path;
        }

        $signaturePath = null;

        if ($request->filled('signature_drawn')) {
            $drawn = (string) $request->input('signature_drawn');

            if (!preg_match('/^data:image\/png;base64,/', $drawn)) {
                throw ValidationException::withMessages([
                    'signature_drawn' => 'Drawn signature must be PNG data.',
                ]);
            }

            $raw = base64_decode(substr($drawn, strpos($drawn, ',') + 1), true);

            if ($raw === false) {
                throw ValidationException::withMessages([
                    'signature_drawn' => 'Invalid drawn signature format.',
                ]);
            }

            $signaturePath = 'signatures/' . Str::uuid() . '.png';
            $publicDisk->put($signaturePath, $raw);
        } elseif ($request->hasFile('signature_file')) {
            $signaturePath = $request->file('signature_file')->store('signatures', 'public');
        }

        if ($signaturePath && $publicDisk->exists($signaturePath)) {
            if ($user->signature_path) {
                $publicDisk->delete($user->signature_path);
            }

            $user->signature_path = $signaturePath;
        }

        $user->save();
        
        return back()->with('status', 'profile-updated');
    }


    /**
     * Delete the user's account.
     */
    // public function destroy(Request $request): RedirectResponse
    // {
    //     $request->validate([
    //         'password' => ['required', 'current_password'],
    //     ]);

    //     $user = $request->user();

    //     Auth::logout();

    //     $user->delete();

    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();

    //     return Redirect::to('/');
    // }
}
