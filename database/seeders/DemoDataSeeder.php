<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Accounting\LedgerAccount;
use App\Models\Accounting\Voucher;
use App\Models\Branch;
use App\Models\Cms\Post;
use App\Models\Examinations\Exam;
use App\Models\Examinations\Mark;
use App\Models\Examinations\MarkConfig;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Fees\FeeCollection;
use App\Models\Fees\FeeHead;
use App\Models\Fees\StudentFee;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Hr\HrSection;
use App\Models\Hr\SubjectTeacher;
use App\Models\Messaging\SmsBatch;
use App\Models\Messaging\SmsTemplate;
use App\Models\Organization;
use App\Models\Students\Enrollment;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\TransferCertificate;
use App\Models\User;
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
            $this->command->error('❌ Seeding failed: '.$e->getMessage());
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

        $this->command->info('📈 Organizations: '.Organization::count());
        $this->command->info('🏛️  Branches: '.Branch::count());
        $this->command->info('👥 Users: '.User::count());
        $this->command->info('📅 Academic Years: '.AcademicYear::count());
        $this->command->info('🏫 Classes: '.SchoolClass::count());
        $this->command->info('🔄 Shifts: '.Shift::count());
        $this->command->info('🔤 Sections: '.Section::count());
        $this->command->info('📚 Subjects: '.Subject::count());
        $this->command->info('📋 Class Configurations: '.ClassConfig::count());
        $this->command->info('👨‍🎓 Students: '.Student::count());
        $this->command->info('📝 Student Enrollments: '.Enrollment::count());
        $this->command->info('👨‍👩‍👧‍👦 Guardians: '.Guardian::count());
        $this->command->info('👨‍💼 Employees: '.Employee::count());
        $this->command->info('📋 Designations: '.Designation::count());
        $this->command->info('🏢 HR Sections: '.HrSection::count());
        $this->command->info('📚 Subject Teachers: '.SubjectTeacher::count());
        $this->command->info('📝 Exams: '.Exam::count());
        $this->command->info('🧮 Mark Configs: '.MarkConfig::count());
        $this->command->info('✍️  Marks Entered: '.Mark::count());
        $this->command->info('🏆 Exam Summaries: '.StudentExamSummary::count());
        $this->command->info('💵 Fee Heads: '.FeeHead::count());
        $this->command->info('🧾 Student Fees Assessed: '.StudentFee::count());
        $this->command->info('💳 Fee Collections: '.FeeCollection::count());
        $this->command->info('📒 Ledger Accounts: '.LedgerAccount::count());
        $this->command->info('🧮 Vouchers Posted: '.Voucher::count());
        $this->command->info('💬 SMS Templates: '.SmsTemplate::count());
        $this->command->info('📤 SMS Batches: '.SmsBatch::count());
        $this->command->info('🎓 Transfer Certificates: '.TransferCertificate::count());
        $this->command->info('📰 CMS Posts: '.Post::count());
    }
}
