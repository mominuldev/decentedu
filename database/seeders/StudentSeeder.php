<?php

namespace Database\Seeders;

use App\Models\Students\Student;
use App\Models\Students\Enrollment;
use App\Models\Students\Guardian;
use App\Models\Branch;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Group;
use App\Models\Academic\Category;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    /**
     * Seed sample student data for development/testing.
     * Creates realistic students with enrollments and guardians.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $this->command->info('Seeding Student data...');

        // Get required data
        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations first.');
            return;
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding students for branch: {$branch->name}");

            // Get academic data
            $academicYear = AcademicYear::where('branch_id', $branch->id)
                ->where('is_current', true)
                ->first() ?? AcademicYear::where('branch_id', $branch->id)->first();

            if (!$academicYear) {
                $this->command->warn("No academic year found for branch {$branch->name}");
                continue;
            }

            $classConfigs = ClassConfig::where('branch_id', $branch->id)->get();
            $groups = Group::where('branch_id', $branch->id)->get();
            $categories = Category::where('branch_id', $branch->id)->get();

            if ($classConfigs->isEmpty()) {
                $this->command->warn("No class configurations found for branch {$branch->name}");
                continue;
            }

            // Create sample students per class configuration
            $studentsPerClass = 15; // Create 15 students per class section

            foreach ($classConfigs as $classConfig) {
                $this->command->info("Creating students for {$classConfig->name}...");

                $rollNumber = 1;

                for ($i = 0; $i < $studentsPerClass; $i++) {
                    // Generate Bangladeshi student data
                    $gender = $faker->randomElement(['male', 'female']);
                    $banglaName = $this->generateBanglaName($gender, $faker);

                    // Create student
                    $student = Student::create([
                        'branch_id' => $branch->id,
                        'student_uid' => $this->generateStudentUid($branch->id, $classConfig->id, $rollNumber),
                        'name' => $faker->name($gender === 'male' ? 'male' : 'female'),
                        'name_bn' => $banglaName,
                        'sex' => $gender,
                        'religion' => $faker->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
                        'blood_group' => $faker->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
                        'dob' => $faker->dateTimeBetween('2006-01-01', '2010-12-31')->format('Y-m-d'),
                        'fathers_name' => $faker->name('male'),
                        'mothers_name' => $faker->name('female'),
                        'mobile' => $faker->phoneNumber(),
                        'father_mobile' => $faker->phoneNumber(),
                        'mother_mobile' => $faker->phoneNumber(),
                        'photo_path' => null, // Can be set later
                        'present_address' => $faker->address(),
                        'permanent_address' => $faker->address(),
                        'status' => 'active',
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);

                    // Create enrollment
                    $group = $faker->randomElement([null, $groups->random()?->id]);
                    $category = $faker->randomElement([null, $categories->random()?->id]);

                    Enrollment::create([
                        'branch_id' => $branch->id,
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYear->id,
                        'class_config_id' => $classConfig->id,
                        'group_id' => $group,
                        'category_id' => $category,
                        'roll' => str_pad($rollNumber, 3, '0', STR_PAD_LEFT),
                        'is_current' => true,
                        'enrolled_at' => now()->format('Y-m-d'),
                        'left_at' => null,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);

                    // Create guardians
                    $this->createGuardians($student, $branch->id, $faker);

                    $rollNumber++;
                }

                $this->command->info("Created {$studentsPerClass} students for {$classConfig->name}");
            }

            // Create some students with special statuses
            $this->createSpecialStatusStudents($branch, $academicYear, $classConfigs, $faker);
        }

        $this->command->info('Student data seeded successfully.');
    }

    /**
     * Create guardians for a student
     */
    private function createGuardians(Student $student, int $branchId, $faker): void
    {
        // Father
        Guardian::create([
            'branch_id' => $branchId,
            'student_id' => $student->id,
            'relationship' => 'father',
            'name' => $student->fathers_name,
            'mobile' => $student->father_mobile,
            'email' => $faker->optional()->email(),
            'address' => $faker->address(),
            'occupation' => $faker->randomElement(['Farmer', 'Businessman', 'Teacher', 'Government Employee', 'Private Job']),
            'nid' => $faker->optional()->numerify('################'),
            'is_emergency_contact' => $faker->boolean(70), // 70% chance
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Mother
        Guardian::create([
            'branch_id' => $branchId,
            'student_id' => $student->id,
            'relationship' => 'mother',
            'name' => $student->mothers_name,
            'mobile' => $student->mother_mobile,
            'email' => $faker->optional()->email(),
            'address' => $faker->address(),
            'occupation' => $faker->randomElement(['Housewife', 'Teacher', 'Nurse', 'Private Job']),
            'nid' => $faker->optional()->numerify('################'),
            'is_emergency_contact' => $faker->boolean(30), // 30% chance
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Optional guardian
        if ($faker->boolean(40)) { // 40% chance to have a third guardian
            Guardian::create([
                'branch_id' => $branchId,
                'student_id' => $student->id,
                'relationship' => $faker->randomElement(['guardian', 'other']),
                'name' => $faker->name(),
                'mobile' => $faker->phoneNumber(),
                'email' => $faker->optional()->email(),
                'address' => $faker->address(),
                'occupation' => $faker->randomElement(['Relative', 'Family Friend', 'Local Guardian']),
                'nid' => $faker->optional()->numerify('################'),
                'is_emergency_contact' => $faker->boolean(20),
                'created_by' => 1,
                'updated_by' => 1,
            ]);
        }
    }

    /**
     * Create students with special statuses (transferred, left, passed_out)
     */
    private function createSpecialStatusStudents(
        Branch $branch,
        AcademicYear $academicYear,
        $classConfigs,
        $faker
    ): void {
        $this->command->info('Creating special status students...');

        $statuses = ['transferred', 'left', 'passed_out'];
        $specialStudents = 5; // 5 students per status
        $rollCounter = 900; // High range so it never collides with a class's regular rolls

        foreach ($statuses as $status) {
            for ($i = 0; $i < $specialStudents; $i++) {
                $rollCounter++;
                $gender = $faker->randomElement(['male', 'female']);

                $student = Student::create([
                    'branch_id' => $branch->id,
                    'student_uid' => 'SP-' . strtoupper($status) . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                    'name' => $faker->name($gender === 'male' ? 'male' : 'female'),
                    'name_bn' => $this->generateBanglaName($gender, $faker),
                    'sex' => $gender,
                    'religion' => $faker->randomElement(['Islam', 'Hinduism', 'Christianity']),
                    'blood_group' => $faker->randomElement(['A+', 'B+', 'O+']),
                    'dob' => $faker->dateTimeBetween('2000-01-01', '2008-12-31')->format('Y-m-d'),
                    'fathers_name' => $faker->name('male'),
                    'mothers_name' => $faker->name('female'),
                    'mobile' => $faker->phoneNumber(),
                    'photo_path' => null,
                    'present_address' => $faker->address(),
                    'permanent_address' => $faker->address(),
                    'status' => $status,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);

                // Create a historical enrollment
                Enrollment::create([
                    'branch_id' => $branch->id,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'class_config_id' => $classConfigs->random()->id,
                    'roll' => (string) $rollCounter,
                    'is_current' => false,
                    'enrolled_at' => $faker->dateTimeBetween('2024-01-01', '2024-12-31')->format('Y-m-d'),
                    'left_at' => $status === 'passed_out' ? null : $faker->dateTimeBetween('2024-06-01', '2024-12-31')->format('Y-m-d'),
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }

            $this->command->info("Created {$specialStudents} {$status} students");
        }
    }

    /**
     * Generate a unique student UID
     */
    private function generateStudentUid(int $branchId, int $classConfigId, int $roll): string
    {
        $year = date('Y');
        $branchCode = sprintf('%02d', $branchId);
        $classCode = sprintf('%03d', $classConfigId);
        $rollCode = sprintf('%04d', $roll);

        return "{$year}-{$branchCode}{$classCode}-{$rollCode}";
    }

    /**
     * Generate a realistic Bangla name
     */
    private function generateBanglaName(string $gender, $faker): string
    {
        $maleNames = ['মোহাম্মদ আলী', 'আব্দুল্লাহ', 'আব্দুর রহিম', 'সাইফুল ইসলাম', 'রাকিবুল হাসান', 'তানভীর আহমেদ', 'জুবায়ের আহমেদ', 'শফিকুল ইসলাম'];
        $femaleNames = ['ফাতেমা খানম', 'আয়শা খানম', 'রেহেনা পারভীন', 'নুসরাত জাহান', 'শারমিন আক্তার', 'সুমাইয়া আক্তার', 'তাসনিম আক্তার', 'মরিয়া খানম'];

        if ($gender === 'male') {
            return $faker->randomElement($maleNames);
        }

        return $faker->randomElement($femaleNames);
    }
}