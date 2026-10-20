<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Exports\ArrayExport;
use App\Http\Resources\TripResource;
use App\Models\Expense;
use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    // GET /dashboard
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $perStatus = Trip::visibleTo($user)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // semua status selalu tampil, yang kosong = 0
        $status = collect(TripStatus::cases())
            ->mapWithKeys(fn ($s) => [$s->value => (int) ($perStatus[$s->value] ?? 0)]);

        $totalBiaya = Expense::whereHas('trip', fn ($q) => $q->visibleTo($user))->sum('jumlah');

        $data = [
            'total_perjalanan' => $status->sum(),
            'per_status'       => $status,
            'total_biaya'      => (float) $totalBiaya,
            'terbaru'          => TripResource::collection(
                Trip::with('user')->withSum('expenses', 'jumlah')
                    ->visibleTo($user)->latest()->limit(5)->get()
            )->resolve(),
        ];

        if ($user->hasAnyRole(['atasan', 'admin'])) {
            $menunggu = Trip::where('status', TripStatus::Diajukan->value)
                ->where('user_id', '!=', $user->id);

            if (! $user->hasRole('admin')) {
                $menunggu->whereIn('user_id', $user->bawahan()->pluck('id'));
            }

            $data['menunggu_approval'] = $menunggu->count();
        }

        return response()->json(['data' => $data]);
    }

    // GET /reports/trips?format=json|pdf|xlsx&status=&tujuan=&dari=&sampai=&user_id=
    public function trips(Request $request)
    {
        $request->validate(['format' => ['nullable', 'in:json,pdf,xlsx']]);
        $user = $request->user();

        $trips = Trip::with('user')
            ->withSum('expenses', 'jumlah')
            ->visibleTo($user)
            ->filter($request->only(['user_id', 'tujuan', 'status', 'dari', 'sampai']))
            ->orderBy('tanggal_berangkat')
            ->get();

        $headings = ['No', 'Pegawai', 'Departemen', 'Tujuan', 'Berangkat', 'Pulang', 'Status', 'Total Biaya (Rp)'];

        $rows = $trips->values()->map(fn ($t, $i) => [
            $i + 1,
            $t->user->name,
            $t->user->department,
            $t->tujuan,
            $t->tanggal_berangkat->format('d-m-Y'),
            $t->tanggal_pulang->format('d-m-Y'),
            $t->status->label(),
            (float) ($t->expenses_sum_jumlah ?? 0),
        ])->all();

        $footer = 'Total biaya: Rp ' . number_format((float) $trips->sum('expenses_sum_jumlah'), 0, ',', '.');

        return $this->output($request, 'Laporan Perjalanan Dinas', 'laporan-perjalanan', $headings, $rows, $footer);
    }

    // GET /reports/expenses?format=json|pdf|xlsx&jenis=&trip_id=&status=&dari=&sampai=
    public function expenses(Request $request)
    {
        $request->validate(['format' => ['nullable', 'in:json,pdf,xlsx']]);
        $user = $request->user();

        $expenses = Expense::with('trip.user')
            ->withCount('receipts')
            ->whereHas('trip', fn ($q) => $q
                ->visibleTo($user)
                ->filter($request->only(['user_id', 'tujuan', 'status', 'dari', 'sampai'])))
            ->when($request->query('jenis'), fn ($q, $v) => $q->where('jenis', $v))
            ->when($request->query('trip_id'), fn ($q, $v) => $q->where('trip_id', $v))
            ->orderBy('tanggal')
            ->get();

        $headings = ['No', 'Pegawai', 'Tujuan', 'Tanggal', 'Jenis', 'Keterangan', 'Jumlah (Rp)', 'Jumlah Bukti'];

        $rows = $expenses->values()->map(fn ($e, $i) => [
            $i + 1,
            $e->trip->user->name,
            $e->trip->tujuan,
            $e->tanggal->format('d-m-Y'),
            ucfirst($e->jenis),
            $e->keterangan,
            (float) $e->jumlah,
            $e->receipts_count,
        ])->all();

        $footer = 'Total pengeluaran: Rp ' . number_format((float) $expenses->sum('jumlah'), 0, ',', '.');

        return $this->output($request, 'Laporan Pengeluaran Perjalanan Dinas', 'laporan-pengeluaran', $headings, $rows, $footer);
    }

    private function output(Request $request, string $title, string $filename, array $headings, array $rows, string $footer)
    {
        return match ($request->query('format', 'json')) {
            'pdf' => Pdf::loadView('reports.table', [
                'title'    => $title,
                'user'     => $request->user()->name,
                'headings' => $headings,
                'rows'     => $rows,
                'footer'   => $footer,
            ])->setPaper('a4', 'landscape')->download("{$filename}.pdf"),

            'xlsx' => Excel::download(new ArrayExport($rows, $headings), "{$filename}.xlsx"),

            default => response()->json([
                'title'    => $title,
                'headings' => $headings,
                'rows'     => $rows,
                'footer'   => $footer,
            ]),
        };
    }
}
