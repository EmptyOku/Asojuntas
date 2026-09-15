<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('guard_name', 50)->default('web')->after('name');
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('guard_name', 50)->default('web')->after('name');
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        DB::table('role_has_permissions')->insertUsing(
            ['permission_id', 'role_id'],
            DB::table('role_permissions')->select(['permission_id', 'role_id'])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('role_has_permissions as existing')
                        ->whereColumn('existing.permission_id', 'role_permissions.permission_id')
                        ->whereColumn('existing.role_id', 'role_permissions.role_id');
                }),
        );

        DB::table('model_has_roles')->insertUsing(
            ['role_id', 'model_type', 'model_id'],
            DB::table('user_roles')->selectRaw("role_id, 'App\\Models\\User', user_id")
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('model_has_roles as existing')
                        ->whereColumn('existing.role_id', 'user_roles.role_id')
                        ->whereColumn('existing.model_id', 'user_roles.user_id')
                        ->where('existing.model_type', 'App\\Models\\User');
                }),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn('guard_name');
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn('guard_name');
        });
    }
};