<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    // Lihat: mengikuti hak lihat perjalanan (pemilik, atasan, admin, HRD/Finance).
    public function view(User $user, Payment $payment): bool
    {
        return $user->can('view', $payment->trip);
    }

    // Verifikasi / tolak / bayar: hanya admin dan finance.
    public function manage(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole(['admin', 'finance']);
    }
}
