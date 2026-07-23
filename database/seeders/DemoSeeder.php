<?php

namespace Database\Seeders;

use App\Models\Academic\Category;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Group;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Academic\AcademicYear;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Global (team-less) role context for foundational roles.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $org = Organization::firstOrCreate(
            ['slug' => 'decentedu-group'],
            ['name' => 'DecentEdu Group', 'status' => true],
        );

        $branchNames = [
            'Demo IT School',
            'Demo College',
            'Demo School',
            'Horipur Girls High School',
            'Masud-UL Haque Institute',
        ];

        $branches = collect($branchNames)->map(function (string $name, int $i) use ($org) {
            return Branch::firstOrCreate(
                ['organization_id' => $org->id, 'code' => 'BR'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                ['name' => $name, 'status' => true],
            );
        });

        // Global role definitions (team_id null); assignments are per-branch below.
        $roles = collect(['Super Admin', 'Admin', 'Accountant', 'Exam Controller', 'Teacher'])
            ->mapWithKeys(fn (string $r) => [$r => Role::firstOrCreate(['name' => $r, 'guard_name' => 'web'])]);

        $admin = User::firstOrCreate(
            ['email' => 'demo@decentedu.test'],
            [
                'organization_id' => $org->id,
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'status' => true,
            ],
        );

        // Attach all branches; pin the first as default.
        $sync = $branches->mapWithKeys(fn (Branch $b, int $i) => [$b->id => ['is_default' => $i === 0]]);
        $admin->branches()->sync($sync);

        // Assign Super Admin within each branch's team context (team_id = branch id).
        foreach ($branches as $branch) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            if (! $admin->hasRole($roles['Super Admin'])) {
                $admin->assignRole($roles['Super Admin']);
            }
        }

        // Academic reference data for the first branch so the UI has content.
        $this->seedAcademic($branches->first());

        $this->command?->info('Seeded demo org, '.$branches->count().' branches, and demo@decentedu.test / password');
    }

    private function seedAcademic(Branch $branch): void
    {
        // Scope creates to this branch (BelongsToBranch auto-fills branch_id).
        app(BranchContext::class)->set($branch->id);

        AcademicYear::firstOrCreate(['name' => '2026'], ['is_current' => true, 'serial' => 1]);
        AcademicYear::firstOrCreate(['name' => '2025'], ['serial' => 2]);

        $classes = ['Six', 'Seven', 'Eight', 'Nine', 'Ten'];
        foreach ($classes as $i => $c) {
            SchoolClass::firstOrCreate(['name' => $c], ['serial' => $i + 1]);
        }
        foreach (['Morning', 'Day'] as $i => $s) {
            Shift::firstOrCreate(['name' => $s], ['serial' => $i + 1]);
        }
        foreach (['A', 'B', 'C'] as $i => $s) {
            Section::firstOrCreate(['name' => $s], ['serial' => $i + 1]);
        }
        foreach (['Science', 'Business Studies', 'Humanities'] as $i => $g) {
            Group::firstOrCreate(['name' => $g], ['serial' => $i + 1]);
        }
        foreach (['General', 'Residential'] as $i => $c) {
            Category::firstOrCreate(['name' => $c], ['serial' => $i + 1]);
        }
        foreach (['Bangla 1st', 'Bangla 2nd', 'English 1st', 'English 2nd', 'Mathematics', 'Science', 'ICT', 'Religion'] as $i => $sub) {
            Subject::firstOrCreate(['name' => $sub], ['serial' => $i + 1]);
        }

        // Class configs: each class in the Day shift, section A.
        $day = Shift::where('name', 'Day')->first();
        $secA = Section::where('name', 'A')->first();
        foreach (SchoolClass::orderBy('serial')->get() as $i => $class) {
            ClassConfig::firstOrCreate(
                ['class_id' => $class->id, 'shift_id' => $day->id, 'section_id' => $secA->id],
                ['serial' => $i + 1],
            );
        }

        app(BranchContext::class)->set(null);
    }
}
