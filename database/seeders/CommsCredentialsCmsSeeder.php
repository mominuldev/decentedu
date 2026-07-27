<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Credentials\CertificateController;
use App\Http\Controllers\Api\Credentials\TestimonialController;
use App\Http\Controllers\Api\Credentials\TransferCertificateController;
use App\Jobs\SendSmsBatch;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Branch;
use App\Models\Cms\Event;
use App\Models\Cms\Menu;
use App\Models\Cms\Notice;
use App\Models\Cms\Page;
use App\Models\Cms\Post;
use App\Models\Cms\Taxonomy;
use App\Models\Cms\Term;
use App\Models\Credentials\IdCardTemplate;
use App\Models\Hr\Employee;
use App\Models\Messaging\Contact;
use App\Models\Messaging\SmsBalance;
use App\Models\Messaging\SmsTemplate;
use App\Models\Students\Enrollment;
use App\Models\User;
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

        $adminUser = User::first();

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

        // ---- A hierarchical page tree, with a block-composed "About" page ----------------
        $home = Page::firstOrCreate(
            ['branch_id' => $branchId, 'parent_id' => null, 'slug' => 'home'],
            ['title' => 'Home', 'path' => 'home', 'template' => 'home', 'status' => 'published',
                'published_at' => now(), 'created_by' => $adminId, 'updated_by' => $adminId],
        );

        $about = Page::firstOrCreate(
            ['branch_id' => $branchId, 'parent_id' => null, 'slug' => 'about-us'],
            ['title' => 'About Us', 'path' => 'about-us', 'template' => 'default', 'status' => 'published',
                'excerpt' => 'Learn about our institution.', 'published_at' => now(),
                'created_by' => $adminId, 'updated_by' => $adminId],
        );

        if ($about->blocks()->count() === 0) {
            $about->blocks()->create([
                'type' => 'hero', 'position' => 0, 'is_visible' => true,
                'payload' => ['heading' => 'Welcome to '.(Branch::find($branchId)?->name ?? 'our institution'),
                    'subtitle' => 'Excellence in Education'],
            ]);
            $about->blocks()->create([
                'type' => 'rich_text', 'position' => 1, 'is_visible' => true,
                'payload' => ['content' => '<p>We are committed to nurturing every student toward their full potential.</p>'],
            ]);
        }

        // A child page to exercise nested paths.
        Page::firstOrCreate(
            ['branch_id' => $branchId, 'parent_id' => $about->id, 'slug' => 'our-team'],
            ['title' => 'Our Team', 'path' => 'about-us/our-team', 'template' => 'default',
                'status' => 'published', 'published_at' => now(), 'created_by' => $adminId, 'updated_by' => $adminId],
        );

        // ---- Taxonomy + terms -------------------------------------------------------------
        $categories = Taxonomy::firstOrCreate(
            ['branch_id' => $branchId, 'slug' => 'category'],
            ['name' => 'Category', 'hierarchical' => true, 'object_types' => ['post', 'event']],
        );
        $news = Term::firstOrCreate(['taxonomy_id' => $categories->id, 'slug' => 'news'], ['name' => 'News']);
        $notices = Term::firstOrCreate(['taxonomy_id' => $categories->id, 'slug' => 'notices'], ['name' => 'Notices']);

        // ---- Blog posts, categorised ------------------------------------------------------
        $admission = Post::firstOrCreate(
            ['branch_id' => $branchId, 'slug' => 'admission-notice'],
            ['title' => 'Admission Notice', 'excerpt' => 'Admissions are now open.',
                'body' => '<p>Admissions are now open for the new academic year.</p>', 'author_id' => $adminId,
                'status' => 'published', 'is_featured' => true, 'reading_time' => 1, 'published_at' => now(),
                'created_by' => $adminId, 'updated_by' => $adminId],
        );
        $admission->terms()->syncWithoutDetaching([$notices->id]);

        $sports = Post::firstOrCreate(
            ['branch_id' => $branchId, 'slug' => 'annual-sports-day'],
            ['title' => 'Annual Sports Day', 'excerpt' => 'Our annual sports day is coming up.',
                'body' => '<p>The annual sports day will be held next month.</p>', 'author_id' => $adminId,
                'status' => 'published', 'reading_time' => 1, 'published_at' => now(),
                'created_by' => $adminId, 'updated_by' => $adminId],
        );
        $sports->terms()->syncWithoutDetaching([$news->id]);

        // ---- Notice categories + dated notices (with a downloadable file) -----------------
        $noticeCats = Taxonomy::firstOrCreate(
            ['branch_id' => $branchId, 'slug' => 'notice-category'],
            ['name' => 'Notice Category', 'hierarchical' => false, 'object_types' => ['notice']],
        );
        $catAdmission = Term::firstOrCreate(['taxonomy_id' => $noticeCats->id, 'slug' => 'admission'], ['name' => 'Admission']);
        $catExam = Term::firstOrCreate(['taxonomy_id' => $noticeCats->id, 'slug' => 'exam'], ['name' => 'Exam']);
        $catEvent = Term::firstOrCreate(['taxonomy_id' => $noticeCats->id, 'slug' => 'event'], ['name' => 'Event']);
        $catStipend = Term::firstOrCreate(['taxonomy_id' => $noticeCats->id, 'slug' => 'stipend'], ['name' => 'Stipend']);
        $catHoliday = Term::firstOrCreate(['taxonomy_id' => $noticeCats->id, 'slug' => 'holiday'], ['name' => 'Holiday']);

        $notices = [
            ['Class Six Admission Notice for 2026', now()->subDays(2), $catAdmission, true],
            ['Half-Yearly Exam Schedule and Seat Plan', now()->subDays(11), $catExam, false],
            ['Science Fair 2026 — Project Submission Guidelines', now()->subDays(19), $catEvent, false],
            ['Urgent Notice Regarding Stipend Data Update', now()->subDays(28), $catStipend, false],
            ['Summer Vacation and Class Resumption Dates', now()->subMonth(), $catHoliday, false],
        ];
        foreach ($notices as [$title, $date, $term, $important]) {
            $notice = Notice::firstOrCreate(
                ['branch_id' => $branchId, 'slug' => str($title)->slug()->value()],
                ['title' => $title, 'notice_date' => $date, 'is_important' => $important, 'status' => 'published',
                    'published_at' => $date, 'body' => '<p>'.$title.'.</p>', 'created_by' => $adminId, 'updated_by' => $adminId],
            );
            $notice->terms()->syncWithoutDetaching([$term->id]);
        }

        // ---- Events -----------------------------------------------------------------------
        $events = [
            ['Annual Science Fair 2026', now()->addWeeks(2)->setTime(9, 0), 'School auditorium', $catEvent],
            ['Parent-Teacher Meeting', now()->addWeeks(3)->setTime(10, 30), 'Main hall', $catEvent],
        ];
        foreach ($events as [$title, $start, $location, $term]) {
            $event = Event::firstOrCreate(
                ['branch_id' => $branchId, 'slug' => str($title)->slug()->value()],
                ['title' => $title, 'starts_at' => $start, 'ends_at' => (clone $start)->addHours(3), 'location' => $location,
                    'status' => 'published', 'published_at' => now(), 'body' => '<p>'.$title.'.</p>',
                    'created_by' => $adminId, 'updated_by' => $adminId],
            );
            $event->terms()->syncWithoutDetaching([$term->id]);
        }

        // ---- A header menu linking pages, a post, and a term ------------------------------
        $menu = Menu::firstOrCreate(['branch_id' => $branchId, 'key' => 'header'], ['name' => 'Main Menu', 'is_active' => true]);
        if ($menu->items()->count() === 0) {
            $menu->items()->create(['label' => 'Home', 'linkable_type' => 'page', 'linkable_id' => $home->id, 'position' => 0]);
            $menu->items()->create(['label' => 'About Us', 'linkable_type' => 'page', 'linkable_id' => $about->id, 'position' => 1]);
            $menu->items()->create(['label' => 'Admission', 'linkable_type' => 'post', 'linkable_id' => $admission->id, 'position' => 2]);
            $menu->items()->create(['label' => 'News', 'linkable_type' => 'term', 'linkable_id' => $news->id, 'position' => 3]);
        }
    }
}
