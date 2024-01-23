<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role = Role::find(1);

        // Retrieve a specific permission by name
        $permission = Permission::where('name', 'permissions.index')->first();

        // Check if the permission exists before giving it to the role
        if ($permission) {
            $role->givePermissionTo($permission);
        } else {
            // Handle the case where the permission doesn't exist
            // You might want to create the permission or log an error here
        }
    }
}
