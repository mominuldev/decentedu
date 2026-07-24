<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationBranchSeeder extends Seeder
{
    /**
     * Seed organizations and branches for development/testing.
     * Creates a demo organization with multiple branches and admin users.
     */
    public function run(): void
    {
        $this->command->info('Seeding Organizations and Branches...');

        // Create a demo organization
        $organization = Organization::firstOrCreate(
            ['name' => 'Safe Eduman Demo'],
            [
                'email' => 'info@safeeduman.com',
                'phone' => '+880 1700-000000',
                'address' => 'Dhaka, Bangladesh',
                'website' => 'https://safeeduman.com',
                'status' => true,
            ]
        );

        $this->command->info("Created organization: {$organization->name}");

        // Create demo branches
        $branches = [
            [
                'name' => 'Demo IT School',
                'name_bn' => 'ডেমো আইটি স্কুল',
                'code' => 'DIT',
                'address' => 'Sector 10, Dhaka',
                'phone' => '+880 1700-000001',
                'logo_path' => null,
            ],
            [
                'name' => 'Demo College',
                'name_bn' => 'ডেমো কলেজ',
                'code' => 'DCOL',
                'address' => 'Sector 4, Dhaka',
                'phone' => '+880 1700-000002',
                'logo_path' => null,
            ],
            [
                'name' => 'Demo School',
                'name_bn' => 'ডেমো স্কুল',
                'code' => 'DSCH',
                'address' => 'Mirpur, Dhaka',
                'phone' => '+880 1700-000003',
                'logo_path' => null,
            ],
        ];

        foreach ($branches as $branchData) {
            $branch = Branch::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $branchData['code'],
                ],
                [
                    'name' => $branchData['name'],
                    'name_bn' => $branchData['name_bn'],
                    'address' => $branchData['address'],
                    'phone' => $branchData['phone'],
                    'logo_path' => $branchData['logo_path'],
                    'status' => true,
                ]
            );

            $this->command->info("Created branch: {$branch->name} ({$branch->code})");

            // Create admin user for each branch
            $adminUser = User::firstOrCreate(
                [
                    'email' => "admin@{$branch->code}.safeeduman.com",
                ],
                [
                    'name' => "Admin {$branch->name}",
                    'password' => Hash::make('password'), // Default password for demo
                    'mobile' => '+880 1'.str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                    'status' => true,
                ]
            );

            // Attach user to branch with admin role
            $existingPivot = DB::table('branch_user')
                ->where('branch_id', $branch->id)
                ->where('user_id', $adminUser->id)
                ->first();

            if (! $existingPivot) {
                DB::table('branch_user')->insert([
                    'branch_id' => $branch->id,
                    'user_id' => $adminUser->id,
                    'role' => 'admin',
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Assign admin permissions
                $adminUser->assignRole('admin');
            }

            $this->command->info("Created admin user: {$adminUser->email} (password: password)");
        }

        // Create a super admin user with access to all branches
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@safeeduman.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('superpassword'), // Default password for demo
                'mobile' => '+880 1700-000000',
                'status' => true,
            ]
        );

        // Assign super admin role
        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }

        // Give super admin access to all branches
        foreach ($branches as $branchData) {
            $branch = Branch::where('code', $branchData['code'])->first();
            if ($branch) {
                $existingPivot = DB::table('branch_user')
                    ->where('branch_id', $branch->id)
                    ->where('user_id', $superAdmin->id)
                    ->first();

                if (! $existingPivot) {
                    DB::table('branch_user')->insert([
                        'branch_id' => $branch->id,
                        'user_id' => $superAdmin->id,
                        'role' => 'super_admin',
                        'status' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Created super admin: superadmin@safeeduman.com (password: superpassword)');

        $this->command->info('Organizations and Branches seeded successfully.');
        $this->command->info('');
        $this->command->info('🔑 Demo Login Credentials:');
        $this->command->info('   Super Admin: superadmin@safeeduman.com / superpassword');
        $this->command->info('   Branch Admins: admin@DIT.safeeduman.com / password');
        $this->command->info('                  (and so on for other branches)');
    }
}
