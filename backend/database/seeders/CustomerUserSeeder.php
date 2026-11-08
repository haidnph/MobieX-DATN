<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerUserSeeder extends Seeder
{
    /**
     * Seed customer and administrator test accounts with default addresses.
     */
    public function run(): void
    {
        // 1. Customer Account
        $customer = User::firstOrCreate(
            ['email' => 'customer@mobilex.tech'],
            [
                'name' => 'Nguyễn Văn Khách Hàng',
                'password' => Hash::make('Password123456!'),
                'phone' => '0987654321',
                'is_active' => true,
            ]
        );

        // Seed default address for customer
        UserAddress::firstOrCreate(
            [
                'user_id' => $customer->id,
                'phone' => '0987654321',
            ],
            [
                'label' => 'Nhà riêng',
                'full_name' => 'Nguyễn Văn Khách Hàng',
                'address_line' => 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô',
                'ward' => 'Phương Canh',
                'district' => 'Nam Từ Liêm',
                'city' => 'Hà Nội',
                'is_default' => true,
            ]
        );

        // 2. Administrator Account
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@mobilex.tech'],
            [
                'name' => 'MobieX Quản Trị Viên',
                'password' => Hash::make('AdminPass123456!'),
                'phone' => '0912345678',
                'is_active' => true,
            ]
        );

        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
