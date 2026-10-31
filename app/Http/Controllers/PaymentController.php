<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\TripStatus;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\PaymentDecided;
use App\Notifications\PaymentRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    // GET /payments?status=&trip_id=&q=&per_page=
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status'   => ['nullable', 'in:diajukan,disetujui,ditolak,dibayar'],
            'trip_id'  => ['nullable', 'integer'],
        ]);

        $query = Payment::with(['trip.user', 'requester', 'verifier', 'payer'])
            ->visibleTo($request->user())
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('trip_id'), fn ($q, $v) => $q->where('trip_id', $v))
            ->when($request->query('q'), function ($q, $v) {
                $q->where(function ($inner) use ($v) {
                    $inner->whereHas('trip', fn ($t) => $t->where('tujuan', 'like', "%{$v}%"))
                        ->orWhereHas('trip.user', fn ($u) => $u->where('name', 'like', "%{$v}%"));
                });
            })
            ->latest();

        return PaymentResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    // GET /payments/{payment}
    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);

        return new PaymentResource($this->loaded($payment));
    }

    // POST /trips/{trip}/payments
    public function store(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        if ($trip->user_id !== $request->user()->id) {
            abort(403, 'Hanya pemilik perjalanan yang dapat mengajukan pembayaran.');
        }

        if (! in_array($trip->status, [TripStatus::Selesai, TripStatus::Lengkap], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pembayaran hanya dapat diajukan setelah perjalanan selesai.'],
            ]);
        }

        $total = (float) $trip->expenses()->sum('jumlah');

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'trip' => ['Belum ada pengeluaran yang dapat dibayarkan pada perjalanan ini.'],
            ]);
        }

        $sudahAda = $trip->payments()
            ->whereIn('status', [
                PaymentStatus::Diajukan->value,
                PaymentStatus::Disetujui->value,
                PaymentStatus::Dibayar->value,
            ])
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'trip' => ['Perjalanan ini sudah memiliki pengajuan pembayaran aktif.'],
            ]);
        }

        $data = $request->validate([
            'jumlah_diajukan' => ['nullable', 'numeric', 'min:1'],
            'catatan'         => ['nullable', 'string', 'max:500'],
        ]);

        $jumlah = (float) ($data['jumlah_diajukan'] ?? $total);

        if ($jumlah > $total) {
            throw ValidationException::withMessages([
                'jumlah_diajukan' => [
                    'Jumlah yang diajukan tidak boleh melebihi total pengeluaran (Rp '
                    . number_format($total, 0, ',', '.') . ').',
                ],
            ]);
        }

        $payment = $trip->payments()->create([
            'requested_by'    => $request->user()->id,
            'jumlah_diajukan' => $jumlah,
            'status'          => PaymentStatus::Diajukan,
            'catatan'         => $data['catatan'] ?? null,
        ]);

        $this->notifyFinance($payment);

        return (new PaymentResource($this->loaded($payment)))
            ->response()
            ->setStatusCode(201);
    }

    // POST /payments/{payment}/verify
    public function verify(Request $request, Payment $payment)
    {
        $this->authorize('manage', $payment);
        $this->ensureTransition($payment, PaymentStatus::Disetujui);

        $data = $request->validate([
            'jumlah_disetujui' => ['nullable', 'numeric', 'min:1'],
            'catatan'          => ['nullable', 'string', 'max:500'],
        ]);

        $jumlah = (float) ($data['jumlah_disetujui'] ?? $payment->jumlah_diajukan);

        if ($jumlah > (float) $payment->jumlah_diajukan) {
            throw ValidationException::withMessages([
                'jumlah_disetujui' => ['Jumlah yang disetujui tidak boleh melebihi jumlah yang diajukan.'],
            ]);
        }

        $payment->update([
            'jumlah_disetujui' => $jumlah,
            'status'           => PaymentStatus::Disetujui,
            'catatan'          => $data['catatan'] ?? $payment->catatan,
            'verified_by'      => $request->user()->id,
            'verified_at'      => now(),
        ]);

        $this->notifyOwner($payment, PaymentStatus::Disetujui, $data['catatan'] ?? null);

        return new PaymentResource($this->loaded($payment));
    }

    // POST /payments/{payment}/reject
    public function reject(Request $request, Payment $payment)
    {
        $this->authorize('manage', $payment);

        if (! in_array($payment->status, [PaymentStatus::Diajukan, PaymentStatus::Disetujui], true)) {
            throw ValidationException::withMessages([
                'status' => ["Pembayaran berstatus '{$payment->status->value}' tidak dapat ditolak."],
            ]);
        }

        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:500'],
        ]);

        $payment->update([
            'status'      => PaymentStatus::Ditolak,
            'catatan'     => $data['catatan'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $this->notifyOwner($payment, PaymentStatus::Ditolak, $data['catatan']);

        return new PaymentResource($this->loaded($payment));
    }

    // POST /payments/{payment}/pay  (multipart: metode, no_referensi, catatan, bukti)
    public function pay(Request $request, Payment $payment)
    {
        $this->authorize('manage', $payment);
        $this->ensureTransition($payment, PaymentStatus::Dibayar);

        $data = $request->validate([
            'metode'       => ['required', 'in:transfer,tunai'],
            'no_referensi' => ['required_if:metode,transfer', 'nullable', 'string', 'max:100'],
            'catatan'      => ['nullable', 'string', 'max:500'],
            'bukti'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        $buktiPath = $payment->bukti_path;

        if ($request->hasFile('bukti')) {
            if ($buktiPath) {
                Storage::disk('public')->delete($buktiPath);
            }

            $buktiPath = $request->file('bukti')->store("payments/{$payment->id}", 'public');
        }

        $payment->update([
            'status'       => PaymentStatus::Dibayar,
            'metode'       => $data['metode'],
            'no_referensi' => $data['no_referensi'] ?? null,
            'catatan'      => $data['catatan'] ?? $payment->catatan,
            'bukti_path'   => $buktiPath,
            'paid_by'      => $request->user()->id,
            'paid_at'      => now(),
        ]);

        $this->notifyOwner($payment, PaymentStatus::Dibayar, $data['catatan'] ?? null);

        return new PaymentResource($this->loaded($payment));
    }

    private function ensureTransition(Payment $payment, PaymentStatus $next): void
    {
        if (! $payment->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Pembayaran berstatus '{$payment->status->label()}' tidak bisa diubah ke '{$next->label()}'.",
                ],
            ]);
        }
    }

    private function notifyFinance(Payment $payment): void
    {
        $recipients = User::whereHas('roles', fn ($q) => $q->where('name', 'finance'))->get();

        if ($recipients->isEmpty()) {
            $recipients = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
        }

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new PaymentRequested($payment));
        }
    }

    private function notifyOwner(Payment $payment, PaymentStatus $status, ?string $catatan): void
    {
        $payment->trip->user->notify(new PaymentDecided($payment, $status, $catatan));
    }

    private function loaded(Payment $payment): Payment
    {
        return $payment->load(['trip.user', 'requester', 'verifier', 'payer']);
    }
}
