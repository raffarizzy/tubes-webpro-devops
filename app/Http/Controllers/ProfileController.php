<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // ambil profile dari Node.js
        $profile = Http::get(config('services.node_api.url') . '/api/profile/'.$user->id)->json();

        return view('profile.edit', [
            'user'    => $user,
            'profile' => $profile,
        ]);
    }

    /*
    * Update user's profile picture
    */
    public function update_pfp(Request $request)
    {
        try {
            // 1. VALIDATION
            $request->validate([
                'pfpPath' => 'required|image|mimes:jpg,jpeg,png,webp'
            ], [
                'pfpPath.required' => 'Foto profil wajib diunggah.',
                'pfpPath.image' => 'File harus berupa gambar.',
                'pfpPath.mimes' => 'Format gambar harus JPG, JPEG, PNG, atau WebP.',
            ]);

            $user = $request->user();

            // 2. HAPUS FOTO LAMA
            if ($user->pfpPath && str_contains($user->pfpPath, asset('storage'))) {
                $oldPath = str_replace(asset('storage') . '/', '', $user->pfpPath);
                \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
            }

            // 3. SIMPAN FOTO BARU DENGAN OPTIMASI
            $image = $request->file('pfpPath');
            $filename = hexdec(uniqid()) . '.webp';
            $path = 'avatars/' . $filename;

            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $img = $manager->decode($image);
            
            // Resize PFP ke 300x300 (square crop)
            $img->cover(300, 300);

            \Illuminate\Support\Facades\Storage::disk('public')->put(
                $path, 
                (string) $img->encodeUsingFileExtension('webp', quality: 80) // PFP kualitas agak tinggi dikit 80%
            );

            $avatarUrl = asset('storage/' . $path);

            // 4. SYNC KE NODE.JS
            $response = Http::patch(
                config('services.node_api.url') . "/api/profile/{$user->id}",
                ['pfpPath' => $avatarUrl]
            );

            if ($response->failed()) {
                throw new \Exception('Gagal sinkronisasi ke server profile.');
            }

            // 5. SUCCESS
            return back()->with('success', 'Foto profil berhasil diperbarui');

        } catch (\Throwable $e) {
            // ERROR GLOBAL
            return back()
                ->withErrors(['pfpPath' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
    * Update the user's profile information.
    */
    public function update(Request $request): RedirectResponse
    {
        // Guard: status seller terverifikasi tidak boleh diubah dari form profil
        // (mencegah Elevation of Privilege lewat mass-assignment).
        if ($request->has('is_verified_seller')) {
            abort(403, 'Anda tidak diizinkan mengubah status verifikasi seller.');
        }

        try {
            $user = $request->user();

            // 1. VALIDATION
            $request->validate([
                'name' => 'required|string|max:50',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'birthDate' => 'nullable|date',
                'gender' => 'nullable|in:male,female',
                'current_password' => 'nullable|required_with:password',
                'password' => 'nullable|min:8|confirmed',
            ], [
                'name.required' => 'Username wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'email.unique' => 'Email sudah digunakan.',
                'current_password.required_with' => 'Masukkan password saat ini untuk mengganti password.',
                'password.min' => 'Password minimal 8 karakter.',
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
            ]);

            // 2. CEK PASSWORD SAAT INI
            if ($request->filled('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return back()->withErrors([
                        'current_password' => 'Password saat ini salah.'
                    ]);
                }
            }

            // 3. NORMALISASI NOMOR HP (sebelum ada data yang disimpan)
            $profileData = $request->only(['phone', 'birthDate', 'gender']);

            if ($request->filled('phone')) {
                try {
                    $profileData['phone'] = User::normalizePhone($request->phone);
                } catch (\InvalidArgumentException $e) {
                    return back()
                        ->withErrors(['phone' => $e->getMessage()])
                        ->withInput();
                }
            }

            // 4. UPDATE USER
            $userData = $request->only(['name', 'email']);

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            if ($user->email !== $request->email) {
                $userData['email_verified_at'] = null;
            }

            $user->update($userData);

            // 5. UPDATE PROFILE (NODE)
            $response = Http::patch(
                config('services.node_api.url') . '/api/profile/' . $user->id,
                $profileData
            );

            if ($response->failed()) {
                throw new \Exception('Gagal menyimpan data profil.');
            }

            // 6. SUCCESS
            return back()->with('success', 'Profil berhasil diperbarui');

        } catch (\Throwable $e) {
            return back()
                ->withErrors(['general' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
