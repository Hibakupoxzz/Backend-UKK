<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * Kunci preferensi yang didukung. Nilai default semuanya aktif.
     */
    public const KEYS = ['email', 'in_app', 'travel_reminder', 'payment_updates'];

    // GET /me/notifications/preferences
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->preferences($request->user()->notification_preferences),
        ]);
    }

    // PUT /me/notifications/preferences
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'            => ['sometimes', 'boolean'],
            'in_app'           => ['sometimes', 'boolean'],
            'travel_reminder'  => ['sometimes', 'boolean'],
            'payment_updates'  => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $current = $this->preferences($user->notification_preferences);

        // Hanya kunci yang dikirim yang diubah, sisanya dipertahankan.
        $merged = array_merge($current, array_intersect_key($data, array_flip(self::KEYS)));

        $user->update(['notification_preferences' => $merged]);

        return response()->json([
            'data'    => $merged,
            'message' => 'Preferensi notifikasi berhasil disimpan.',
        ]);
    }

    private function preferences(?array $stored): array
    {
        $stored ??= [];

        return collect(self::KEYS)
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($stored[$key] ?? true)])
            ->all();
    }
}
