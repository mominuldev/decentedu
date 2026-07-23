<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Credentials\CertificateController;
use App\Http\Controllers\Api\Credentials\TestimonialController;
use App\Http\Controllers\Api\Credentials\TransferCertificateController;
use App\Jobs\SendSmsBatch;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Branch;
use App\Models\Cms\Menu;
use App\Models\Cms\Post;
use App\Models\Cms\WebsiteSetting;
use App\Models\Credentials\IdCardTemplate;
use App\Models\Hr\Employee;
use App\Models\Messaging\Contact;
use App\Models\Messaging\SmsBalance;
use App\Models\Messaging\SmsTemplate;
use App\Models\Students\Enrollment;
use App\Services\Sms\SmsGatewayInterface;
use App\Services\Sms\SmsSender;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds Phase 8: SMS templates/contacts/balance/a sample delivery report, a handful of issued
 * credentials (one TC actually flips a student to transferred so the status transition is
 * visible), and a small CMS site (posts, one menu, settings) — for every seeded branch.
 */
class CommsCredentialsCmsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Communications, Credentials & CMS data...');

        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations first.');

            return;
        }

        $adminUser = \App\Models\User::first();

        foreach ($branches as $branch) {
            $this->command->info("Seeding Phase 8 data for branch: {$branch->name}");
            app(BranchContext::class)->set($branch->id);
            if ($adminUser) {
                Auth::login($adminUser);
            }

            $this->seedMessaging($branch->id);
            $this->seedCredentials($branch->id);
            $this->seedCms($branch->id);

            app(BranchContext::class)->set(null);
            Auth::logout();
        }

        $this->command->info('Communications, Credentials & CMS data seeded successfully.');
    }

    private function seedMessaging(int $branchId): void
    {
        SmsTemplate::firstOrCreate(
            ['branch_id' => $branchId, 'name' => 'Absentee Notice'],
            ['type' => 'attendance', 'message' => 'Dear Guardian, {student_name} was absent on {date}.', 'status' => true],
        );
        SmsTemplate::firstOrCreate(
            ['branch_id' => $branchId, 'name' => 'Fee Due Reminder'],
            ['type' => 'fee', 'message' => 'Dear Guardian, a fee payment is due. Please pay at your earliest convenience.', 'status' => true],
        );
        SmsTemplate::firstOrCreate(
            ['branch_id' => $branchId, 'name' => 'General Notice'],
            ['type' => 'general', 'message' => 'This is a notice from the school administration.', 'status' => true],
        );

        Contact::firstOrCreate(
            ['branch_id' => $branchId, 'phone' => '01700000001'],
            ['name' => 'PTA Coordinator', 'type' => 'custom', 'status' => true],
        );
        Contact::firstOrCreate(
            ['branch_id' => $branchId, 'phone' => '01700000002'],
            ['name' => 'Local Education Office', 'type' => 'custom', 'status' => true],
        );

        SmsBalance::firstOrCreate(['branch_id' => $branchId], ['balance' => 500]);

        // One sample completed batch so the delivery-report UI has data immediately.
        $sender = app(SmsSender::class);
        $batch = $sender->send(
            branchId: $branchId,
            audienceType: 'custom_numbers',
            recipients: [
                ['phone' => '01711111111', 'name' => 'Demo Guardian 1'],
                ['phone' => '01722222222', 'name' => 'Demo Guardian 2'],
            ],
            message: 'Welcome to the new term. Please check the notice board for the class routine.',
        );
        (new SendSmsBatch($batch->id, $branchId))->handle(app(SmsGatewayInterface::class));
    }

    private function seedCredentials(int $branchId): void
    {
        $academicYear = AcademicYear::where('branch_id', $branchId)->where('is_current', true)->first()
            ?? AcademicYear::where('branch_id', $branchId)->first();
        $classConfig = ClassConfig::where('branch_id', $branchId)->first();

        if ($academicYear && $classConfig) {
            $studentIds = Enrollment::where('class_config_id', $classConfig->id)->current()
                ->pluck('student_id')->unique()->values();

            $tcController = app(TransferCertificateController::class);
            $testimonialController = app(TestimonialController::class);
            $certificateController = app(CertificateController::class);

            if ($studentId = $studentIds->get(0)) {
                $tcController->store(new Request([
                    'student_id' => $studentId,
                    'issue_date' => now()->subDays(5)->toDateString(),
                    'reason_for_leaving' => 'Family relocation',
                    'academic_year_id' => $academicYear->id,
                    'class_config_id' => $classConfig->id,
                ]));
            }

            if ($studentId = $studentIds->get(1)) {
                $testimonialController->store(new Request([
                    'student_id' => $studentId,
                    'issue_date' => now()->subDays(2)->toDateString(),
                    'character_certificate' => 'Bore a good moral character throughout the study period.',
                    'academic_year_id' => $academicYear->id,
                    'class_config_id' => $classConfig->id,
                ]));
            }

            foreach ($studentIds->slice(2, 2) as $studentId) {
                $certificateController->store(new Request([
                    'student_id' => $studentId,
                    'certificate_type' => 'academic',
                    'issue_date' => now()->subDay()->toDateString(),
                    'description' => 'Awarded for outstanding academic performance.',
                ]));
            }
        }

        IdCardTemplate::firstOrCreate(
            ['branch_id' => $branchId, 'name' => 'Student ID Card'],
            [
                'holder_type' => 'student',
                'fields' => ['photo', 'name', 'roll', 'class', 'blood_group', 'guardian', 'validity'],
                'show_qr' => true,
                'primary_color' => '#5343e0',
                'status' => true,
            ],
        );

        if (Employee::where('branch_id', $branchId)->exists()) {
            IdCardTemplate::firstOrCreate(
                ['branch_id' => $branchId, 'name' => 'Staff ID Card'],
                [
                    'holder_type' => 'employee',
                    'fields' => ['photo', 'name', 'designation', 'blood_group', 'mobile', 'validity'],
                    'show_qr' => false,
                    'primary_color' => '#0f766e',
                    'status' => true,
                ],
            );
        }
    }

    private function seedCms(int $branchId): void
    {
        $adminId = auth()->id();

        $page = Post::firstOrCreate(
            ['branch_id' => $branchId, 'type' => 'page', 'slug' => 'about-us'],
            ['title' => 'About Us', 'body' => '<p>Welcome to our institution.</p>', 'status' => 'published', 'published_at' => now(), 'created_by' => $adminId, 'updated_by' => $adminId],
        );
        $notice = Post::firstOrCreate(
            ['branch_id' => $branchId, 'type' => 'notice', 'slug' => 'admission-notice'],
            ['title' => 'Admission Notice', 'body' => '<p>Admissions are now open for the new academic year.</p>', 'status' => 'published', 'published_at' => now(), 'created_by' => $adminId, 'updated_by' => $adminId],
        );
        Post::firstOrCreate(
            ['branch_id' => $branchId, 'type' => 'news', 'slug' => 'annual-sports-day'],
            ['title' => 'Annual Sports Day', 'body' => '<p>The annual sports day will be held next month.</p>', 'status' => 'draft', 'created_by' => $adminId, 'updated_by' => $adminId],
        );

        $menu = Menu::firstOrCreate(['branch_id' => $branchId, 'name' => 'Main Menu'], ['location' => 'header', 'status' => true]);
        $menu->items()->firstOrCreate(['label' => 'Home'], ['url' => '/', 'serial' => 1]);
        $menu->items()->firstOrCreate(['label' => 'About Us'], ['url' => '/about-us', 'post_id' => $page->id, 'serial' => 2]);
        $menu->items()->firstOrCreate(['label' => 'Notices'], ['url' => '/notices', 'post_id' => $notice->id, 'serial' => 3]);

        WebsiteSetting::firstOrCreate(['branch_id' => $branchId], [
            'site_title' => Branch::find($branchId)?->name,
            'tagline' => 'Excellence in Education',
            'address' => Branch::find($branchId)?->address,
            'phone' => Branch::find($branchId)?->phone,
            'email' => Branch::find($branchId)?->email,
            'status' => true,
        ]);
    }
}
