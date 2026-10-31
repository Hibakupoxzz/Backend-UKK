<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    // GET /notifications?per_page=
    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $perPage = $request->integer('per_page', 15);
        $paginator = $request->user()->notifications()->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (DatabaseNotification $n) => $this->format($n))->all(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // GET /notifications/unread-count
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    // POST /notifications/{notification}/read
    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();

        if ($item->read_at === null) {
            $item->markAsRead();
        }

        return response()->json([
            'data' => $this->format($item->fresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    // POST /notifications/read-all
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'unread_count' => 0,
        ]);
    }

    private function format(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id'         => $notification->id,
            'type'       => $data['type'] ?? class_basename($notification->type),
            'title'      => $data['title'] ?? 'Notifikasi',
            'message'    => $data['message'] ?? '',
            'url'        => $data['url'] ?? null,
            'read_at'    => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
