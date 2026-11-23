<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerUserSeeder extends Seeder
{
    /**
     * Create the non-privileged sample customer used by local demo fixtures.
     */
    public function run(): void
    {
        $customerRole = Role::firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Khách hàng'],
        );

        $customer = User::firstOrCreate(
            ['email' => 'customer@mobilex.tech'],
            [
                'name' => 'Nguyễn Văn Khách Hàng',
                // The seeded customer exists to link demo orders. Do not ship
                // a publicly known password that can be used to sign in.
                'password' => Hash::make(Str::random(48)),
                'phone' => '0987654321',
                'is_active' => true,
            ],
        );

        $customer->roles()->syncWithoutDetaching([$customerRole->id]);

        UserAddress::firstOrCreate(
            [
                'user_id' => $customer->id,
                'phone' => '0987654321',
            ],
            [
                'label' => 'Nhà riêng',
                'full_name' => 'Nguyễn Văn Khách Hàng',
                'address_line' => 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô',
                'ward' => 'Phường Phương Canh',
                'district' => 'Nam Từ Liêm',
                'city' => 'Hà Nội',
                'is_default' => true,
            ],
        );
    }
}
