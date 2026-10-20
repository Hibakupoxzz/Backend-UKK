<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\TripWorkflowService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(private TripWorkflowService $workflow) {}

    // Daftar pengajuan yang menunggu keputusan
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Trip::with('user')
            ->withSum('expenses', 'jumlah')
            ->where('status', TripStatus::Diajukan->value)
            ->where('user_id', '!=', $user->id);

        // atasan hanya melihat bawahan langsungnya, admin melihat semua
        if (! $user->hasRole('admin')) {
            $query->whereIn('user_id', $user->bawahan()->pluck('id'));
        }

        return TripResource::collection($query->latest()->paginate(15));
    }

    public function approve(Request $request, Trip $trip)
    {
        $this->authorize('approve', $trip);

        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $trip = $this->workflow->approve($trip, $request->user(), $data['catatan'] ?? null);

        return new TripResource($this->loaded($trip));
    }

    public function reject(Request $request, Trip $trip)
    {
        $this->authorize('approve', $trip);

        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:500'],
        ]);

        $trip = $this->workflow->reject($trip, $request->user(), $data['catatan']);

        return new TripResource($this->loaded($trip));
    }

    // Selesai -> lengkap (setelah data dan bukti pengeluaran diperiksa)
    public function complete(Request $request, Trip $trip)
    {
        $this->authorize('approve', $trip);

        $trip = $this->workflow->complete($trip);

        return new TripResource($this->loaded($trip));
    }

    private function loaded(Trip $trip): Trip
    {
        return $trip->load(['user', 'needs', 'documents', 'approvals.approver']);
    }
}
