<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\TripApproval;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const ROLES = ['pegawai', 'atasan', 'admin', 'finance', 'hrd'];

    // GET /users?q=nama&role=atasan&per_page=15
    public function index(Request $request)
    {
        $request->validate([
            'role' => ['nullable', Rule::in(self::ROLES)],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::with(['roles', 'atasan'])
            ->when($request->query('q'), fn ($q, $v) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")
            ))
            ->when($request->query('role'), fn ($q, $v) => $q->role($v))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    // POST /users
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8'],
            'role'       => ['required', Rule::in(self::ROLES)],
            'department' => ['nullable', 'string', 'max:100'],
            'no_hp'      => ['nullable', 'string', 'max:30'],
            'jabatan'    => ['nullable', 'string', 'max:100'],
            'is_active'  => ['sometimes', 'boolean'],
            'atasan_id'  => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->assertAtasanValid($data['atasan_id'] ?? null);

        // password otomatis di-hash oleh cast 'hashed' di model User
        $user = User::create(collect($data)->except('role')->all());
        $user->assignRole($data['role']);

        return (new UserResource($user->load(['roles', 'atasan'])))
            ->response()->setStatusCode(201);
    }

    // GET /users/{user}
    public function show(User $user)
    {
        return new UserResource($user->load(['roles', 'atasan']));
    }

    // PUT /users/{user}
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'email'      => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'   => ['nullable', 'string', 'min:8'],
            'role'       => ['sometimes', 'required', Rule::in(self::ROLES)],
            'department' => ['nullable', 'string', 'max:100'],
            'no_hp'      => ['nullable', 'string', 'max:30'],
            'jabatan'    => ['nullable', 'string', 'max:100'],
            'is_active'  => ['sometimes', 'boolean'],
            'atasan_id'  => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (array_key_exists('atasan_id', $data)) {
            $this->assertAtasanValid($data['atasan_id'], $user);
        }

        if ($user->is($request->user()) && isset($data['role']) && $data['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['Tidak bisa menurunkan role akun sendiri.'],
            ]);
        }

        if ($user->is($request->user()) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => ['Tidak bisa menonaktifkan akun sendiri.'],
            ]);
        }

        $attrs = collect($data)->except('role');
        if (empty($data['password'])) {
            $attrs->forget('password'); // kosong = password tidak diganti
        }

        $user->update($attrs->all());

        // Menonaktifkan akun juga mencabut seluruh token agar sesi lama tidak bisa dipakai.
        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $user->tokens()->delete();
        }

        if (isset($data['role'])) {
            $user->syncRoles($data['role']);
        }

        return new UserResource($user->load(['roles', 'atasan']));
    }

    // DELETE /users/{user}
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        if ($user->trips()->exists() || TripApproval::where('approver_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User sudah memiliki riwayat perjalanan atau approval, tidak bisa dihapus.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User dihapus.']);
    }

    // Atasan harus berrole atasan/admin dan bukan dirinya sendiri
    private function assertAtasanValid(?int $atasanId, ?User $self = null): void
    {
        if (! $atasanId) {
            return;
        }

        if ($self && $self->id === $atasanId) {
            throw ValidationException::withMessages(['atasan_id' => ['Atasan tidak boleh diri sendiri.']]);
        }

        if (! User::findOrFail($atasanId)->hasAnyRole(['atasan', 'admin'])) {
            throw ValidationException::withMessages(['atasan_id' => ['User tersebut bukan atasan.']]);
        }
    }
}
