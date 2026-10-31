<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_hp')->nullable()->after('department');
            $table->string('jabatan')->nullable()->after('no_hp');
            $table->string('foto_path')->nullable()->after('jabatan');
            $table->boolean('is_active')->default(true)->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_hp', 'jabatan', 'foto_path', 'is_active']);
        });
    }
};
