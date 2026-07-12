<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'products.view', 'products.manage',
            'orders.view', 'orders.create', 'orders.refund',
            'customers.view', 'customers.manage',
            'suppliers.manage',
            'purchases.manage',
            'settings.manage',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::all());

        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->givePermissionTo([
            'products.view',
            'orders.view', 'orders.create',
            'customers.view', 'customers.manage',
        ]);
        // Deliberately NOT giving cashier 'orders.refund' — see gotcha below.
    }
}