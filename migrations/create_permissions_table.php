<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('roles_permissions', static function (Blueprint $table): void {
            $table->foreignId('role_id');
            $table->foreignId('permission_id');
            $table->timestamps();

            // Indexes
            $table->index('role_id');
            $table->index('permission_id');
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('users_permissions', static function (Blueprint $table): void {
            $table->foreignId('user_id');
            $table->foreignId('permission_id');
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('permission_id');
            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles_permissions');
        Schema::dropIfExists('users_permissions');
    }
};
