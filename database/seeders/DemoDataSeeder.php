<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Master seeder that runs all seeders in the correct order.
     * This creates a complete demo environment with organizations, academic data, students, and employees.
     */
    public function run(): void
    {
        $this->command->info('==========================================');
        $this->command->info('Starting Complete Demo Data Seeding...');
        $this->command->info('==========================================');

        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 1. Seed Organizations and Branches (if needed)
            $this->command->info('\n🏢 Step 1: Organizations & Branches');
            $this->call(OrganizationBranchSeeder::class);

            // 2. Seed Academic Foundation Data
            $this->command->info('\n📚 Step 2: Academic Foundation');
            $this->call(AcademicSeeder::class);

            // 3. Seed Students Data
            $this->command->info('\n👨‍🎓 Step 3: Students');
            $this->call(StudentSeeder::class);

            // 4. Seed HR Data
            $this->command->info('\n👨‍💼 Step 4: HR & Staff');
            $this->call(HrSeeder::class);

            // 5. Seed Routines & Attendance Data
            $this->command->info('\n🗓️ Step 5: Routines & Attendance');
            $this->call(RoutineAttendanceSeeder::class);

            $this->command->info('\n==========================================');
            $this->command->info('✅ Demo Data Seeding Completed Successfully!');
            $this->command->info('==========================================');

            // Show summary statistics
            $this->showSummary();

        } catch (\Exception $e) {
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Display summary statistics of seeded data
     */
    private function showSummary(): void
    {
        $this->command->info('\n📊 Database Summary:');

        $this->command->info('📈 Organizations: ' . \App\Models\Organization::count());
        $this->command->info('🏛️  Branches: ' . \App\Models\Branch::count());
        $this->command->info('👥 Users: ' . \App\Models\User::count());
        $this->command->info('📅 Academic Years: ' . \App\Models\Academic\AcademicYear::count());
        $this->command->info('🏫 Classes: ' . \App\Models\Academic\SchoolClass::count());
        $this->command->info('🔄 Shifts: ' . \App\Models\Academic\Shift::count());
        $this->command->info('🔤 Sections: ' . \App\Models\Academic\Section::count());
        $this->command->info('📚 Subjects: ' . \App\Models\Academic\Subject::count());
        $this->command->info('📋 Class Configurations: ' . \App\Models\Academic\ClassConfig::count());
        $this->command->info('👨‍🎓 Students: ' . \App\Models\Students\Student::count());
        $this->command->info('📝 Student Enrollments: ' . \App\Models\Students\Enrollment::count());
        $this->command->info('👨‍👩‍👧‍👦 Guardians: ' . \App\Models\Students\Guardian::count());
        $this->command->info('👨‍💼 Employees: ' . \App\Models\Hr\Employee::count());
        $this->command->info('📋 Designations: ' . \App\Models\Hr\Designation::count());
        $this->command->info('🏢 HR Sections: ' . \App\Models\Hr\HrSection::count());
        $this->command->info('📚 Subject Teachers: ' . \App\Models\Hr\SubjectTeacher::count());
    }
}