<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    // PUT /me
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'    => ['sometimes', 'required', 'string', 'max:255'],
            'email'   => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp'   => ['nullable', 'string', 'max:30'],
            'jabatan' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update($data);

        return new UserResource($user->load(['roles', 'atasan']));
    }

    // PUT /me/password
    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini tidak cocok.'],
            ]);
        }

        $user->update(['password' => $data['password']]);

        return response()->json(['message' => 'Kata sandi berhasil diubah.']);
    }

    // POST /me/foto (multipart: foto)
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->foto_path) {
            Storage::disk('public')->delete($user->foto_path);
        }

        $user->update([
            'foto_path' => $request->file('foto')->store("avatars/{$user->id}", 'public'),
        ]);

        return new UserResource($user->load(['roles', 'atasan']));
    }

    // DELETE /me/foto
    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->foto_path) {
            Storage::disk('public')->delete($user->foto_path);
            $user->update(['foto_path' => null]);
        }

        return new UserResource($user->load(['roles', 'atasan']));
    }
}
