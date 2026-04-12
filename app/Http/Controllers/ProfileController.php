<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $disk = Storage::disk('local');
            $folder = 'avatars/' . $user->id;
            $filename = $request->file('avatar')->hashName();
            $avatarPath = $request->file('avatar')->storeAs($folder, $filename, 'local');

            $user->avatar = $avatarPath;

            $files = $disk->files($folder);
            $files = collect($files)->sortByDesc(function ($path) use ($disk) {
                return $disk->lastModified($path);
            })->values();

            $files->slice(2)->each(function ($path) use ($disk) {
                $disk->delete($path);
            });
        }

        $plainPassword = $data['password'] ?? null;
        unset($data['password'], $data['current_password']);

        if (!($user->isAdmin() || AccessControl::isHrStaff($user))) {
            unset($data['name']);
        }

        $user->fill($data);

        if (!empty($plainPassword)) {
            $user->password = Hash::make($plainPassword);
        }

        $user->save();

        return Redirect::back()->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        if ($user->archived_at === null) {
            $user->archived_at = now();
            $user->save();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
