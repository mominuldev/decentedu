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

            // 6. Seed Examinations Data
            $this->command->info('\n📝 Step 6: Examinations');
            $this->call(ExaminationSeeder::class);

            // 7. Seed Finance Data (Fees + Accounting)
            $this->command->info('\n💰 Step 7: Finance (Fees & Accounting)');
            $this->call(FinanceSeeder::class);

            // 8. Seed Communications, Credentials & CMS Data
            $this->command->info('\n📣 Step 8: Communications, Credentials & CMS');
            $this->call(CommsCredentialsCmsSeeder::class);

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
        $this->command->info('📝 Exams: ' . \App\Models\Examinations\Exam::count());
        $this->command->info('🧮 Mark Configs: ' . \App\Models\Examinations\MarkConfig::count());
        $this->command->info('✍️  Marks Entered: ' . \App\Models\Examinations\Mark::count());
        $this->command->info('🏆 Exam Summaries: ' . \App\Models\Examinations\StudentExamSummary::count());
        $this->command->info('💵 Fee Heads: ' . \App\Models\Fees\FeeHead::count());
        $this->command->info('🧾 Student Fees Assessed: ' . \App\Models\Fees\StudentFee::count());
        $this->command->info('💳 Fee Collections: ' . \App\Models\Fees\FeeCollection::count());
        $this->command->info('📒 Ledger Accounts: ' . \App\Models\Accounting\LedgerAccount::count());
        $this->command->info('🧮 Vouchers Posted: ' . \App\Models\Accounting\Voucher::count());
        $this->command->info('💬 SMS Templates: ' . \App\Models\Messaging\SmsTemplate::count());
        $this->command->info('📤 SMS Batches: ' . \App\Models\Messaging\SmsBatch::count());
        $this->command->info('🎓 Transfer Certificates: ' . \App\Models\Students\TransferCertificate::count());
        $this->command->info('📰 CMS Posts: ' . \App\Models\Cms\Post::count());
    }
}