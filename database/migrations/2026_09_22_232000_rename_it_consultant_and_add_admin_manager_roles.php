<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $existing = Role::query()->where('name', 'it_consultant')->where('guard_name', 'web')->first();

        if ($existing) {
            $existing->name = 'ict_coordinator';
            $existing->save();
        } else {
            Role::firstOrCreate(['name' => 'ict_coordinator', 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin_manager', 'guard_name' => 'web']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $existing = Role::query()->where('name', 'ict_coordinator')->where('guard_name', 'web')->first();

        if ($existing) {
            $existing->name = 'it_consultant';
            $existing->save();
        }

        Role::query()->where('name', 'admin_manager')->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
