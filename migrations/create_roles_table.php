<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('users_roles', static function (Blueprint $table): void {
            $table->foreignId('user_id');
            $table->foreignId('role_id');
            $table->timestamps();

            // Composite primary key
            $table->primary(['user_id', 'role_id']);
            // Index for reverse lookups (finding users by role)
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users_roles');
    }
};
