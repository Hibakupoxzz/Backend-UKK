<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'atasan_id', 'department', 'no_hp', 'jabatan', 'foto_path', 'is_active', 'notification_preferences'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Preferensi notifikasi dengan default aktif bila belum pernah disimpan.
     */
    public function notificationPreference(string $key): bool
    {
        $preferences = $this->notification_preferences ?? [];

        return (bool) ($preferences[$key] ?? true);
    }

    /**
     * Channel yang aktif untuk pengguna ini.
     */
    public function notificationChannels(): array
    {
        $channels = [];

        if ($this->notificationPreference('email')) {
            $channels[] = 'mail';
        }

        if ($this->notificationPreference('in_app')) {
            $channels[] = 'database';
        }

        return $channels;
    }

    public function atasan()  { return $this->belongsTo(User::class, 'atasan_id'); }
    public function bawahan() { return $this->hasMany(User::class, 'atasan_id'); }
    public function trips()   { return $this->hasMany(Trip::class); }
}
