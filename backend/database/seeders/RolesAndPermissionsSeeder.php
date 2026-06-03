<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // database/seeders/RolesAndPermissionsSeeder.php
    public function run()
    {
        // 重置缓存
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 创建权限
        Permission::create(['name' => 'user.list']);
        Permission::create(['name' => 'user.delete']);
        Permission::create(['name' => 'report.view']);

        // 创建角色并分配权限
        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo(Permission::all());

        $manager = Role::create(['name' => 'manager']);
        $manager->givePermissionTo(['user.list', 'report.view']);
    }
}
