<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Http\Requests\StoreTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\TripDocument;
use App\Services\TripWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TripController extends Controller
{
    public function __construct(private TripWorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Trip::with('user')
            ->withSum('expenses', 'jumlah')
            ->filter($request->only(['user_id', 'tujuan', 'status', 'dari', 'sampai']));

        if ($user->hasRole('admin')) {
            // admin melihat semua
        } elseif ($user->hasRole('atasan')) {
            $ids = $user->bawahan()->pluck('id')->push($user->id);
            $query->whereIn('user_id', $ids);
        } else {
            $query->where('user_id', $user->id);
        }

        return TripResource::collection($query->latest()->paginate(15));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = DB::transaction(function () use ($request) {
            $trip = $request->user()->trips()->create([
                ...$request->safe()->only(['tujuan', 'tanggal_berangkat', 'tanggal_pulang', 'keperluan']),
                'status' => TripStatus::Draft,
            ]);
            $trip->needs()->createMany($request->validated('needs', []));

            return $trip;
        });

        return (new TripResource($this->loaded($trip)))->response()->setStatusCode(201);
    }

    public function show(Trip $trip)
    {
        $this->authorize('view', $trip);

        return new TripResource($this->loaded($trip));
    }

    public function update(StoreTripRequest $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        DB::transaction(function () use ($request, $trip) {
            $trip->update($request->safe()->only(['tujuan', 'tanggal_berangkat', 'tanggal_pulang', 'keperluan']));

            if ($request->has('needs')) {
                $trip->needs()->delete();
                $trip->needs()->createMany($request->validated('needs'));
            }
        });

        return new TripResource($this->loaded($trip));
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        return response()->json(['message' => 'Perjalanan dihapus.']);
    }

    // ---------- Alur status ----------

    public function submit(Trip $trip)
    {
        $this->authorize('submit', $trip);

        return new TripResource($this->loaded($this->workflow->submit($trip)));
    }

    public function start(Trip $trip)
    {
        $this->authorize('start', $trip);

        return new TripResource($this->loaded($this->workflow->start($trip)));
    }

    public function finish(Trip $trip)
    {
        $this->authorize('finish', $trip);

        return new TripResource($this->loaded($this->workflow->finish($trip)));
    }

    // ---------- Dokumen pendukung ----------

    public function uploadDocument(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('manageDocuments', $trip);

        $data = $request->validate([
            'file'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'jenis' => ['nullable', 'string', 'max:100'],
        ]);

        $doc = $trip->documents()->create([
            'file_path' => $request->file('file')->store("trips/{$trip->id}", 'public'),
            'jenis'     => $data['jenis'] ?? null,
        ]);

        return response()->json([
            'id'  => $doc->id,
            'url' => Storage::disk('public')->url($doc->file_path),
        ], 201);
    }

    public function deleteDocument(TripDocument $document): JsonResponse
    {
        $this->authorize('manageDocuments', $document->trip);

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Dokumen dihapus.']);
    }

    private function loaded(Trip $trip): Trip
    {
        return $trip->load(['user', 'needs', 'documents', 'approvals.approver']);
    }
}
