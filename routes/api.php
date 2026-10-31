<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Publik
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| Wajib login (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ---------- Auth ----------
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ---------- Notifikasi in-app ----------
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

    // Preferensi notifikasi akun sendiri
    Route::get('me/notifications/preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('me/notifications/preferences', [NotificationPreferenceController::class, 'update']);

    // Profil akun sendiri
    Route::put('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'password']);
    Route::post('/me/foto', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/me/foto', [ProfileController::class, 'deletePhoto']);

    // ---------- Perjalanan dinas (pegawai) ----------
    Route::apiResource('trips', TripController::class);
    Route::post('trips/{trip}/submit', [TripController::class, 'submit']);   // draft/ditolak -> diajukan
    Route::post('trips/{trip}/start',  [TripController::class, 'start']);    // disetujui -> berjalan
    Route::post('trips/{trip}/finish', [TripController::class, 'finish']);   // berjalan -> selesai

    // Dokumen pendukung perjalanan
    Route::post('trips/{trip}/documents', [TripController::class, 'uploadDocument']);
    Route::delete('documents/{document}', [TripController::class, 'deleteDocument']);

    // ---------- Pengeluaran ----------
    // index & store: /trips/{trip}/expenses
    // show, update, destroy: /expenses/{expense}
    Route::apiResource('trips.expenses', ExpenseController::class)->shallow();

    // Bukti transaksi (foto/dokumen)
    Route::post('expenses/{expense}/receipts', [ExpenseController::class, 'uploadReceipt']);
    Route::delete('receipts/{receipt}', [ExpenseController::class, 'deleteReceipt']);

    // ---------- Pembayaran / reimbursement ----------
    // Pengajuan dibuat oleh pemilik perjalanan, verifikasi & pembayaran oleh finance/admin.
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::post('trips/{trip}/payments', [PaymentController::class, 'store']);

    // ---------- Dashboard & laporan ----------
    Route::get('dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/trips', [ReportController::class, 'trips']);         // ?format=pdf|xlsx
    Route::get('reports/expenses', [ReportController::class, 'expenses']);   // ?format=pdf|xlsx

    // ---------- Approval & validasi (atasan / admin) ----------
    Route::middleware('role:atasan|admin')->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index']);
        Route::post('trips/{trip}/approve',  [ApprovalController::class, 'approve']);
        Route::post('trips/{trip}/reject',   [ApprovalController::class, 'reject']);
        Route::post('trips/{trip}/complete', [ApprovalController::class, 'complete']); // selesai -> lengkap
    });

    // ---------- Verifikasi & pembayaran (finance / admin) ----------
    Route::middleware('role:finance|admin')->group(function () {
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify']);
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject']);
        Route::post('payments/{payment}/pay',    [PaymentController::class, 'pay']);
    });

    // ---------- Data pegawai (admin & HRD) ----------
    Route::middleware('role:admin|hrd')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
    });

    // ---------- Administrator ----------
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['index', 'show']);
    });
});
