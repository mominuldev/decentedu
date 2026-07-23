<?php

namespace Database\Seeders;

use App\Models\Hr\Employee;
use App\Models\Hr\Designation;
use App\Models\Hr\HrSection;
use App\Models\Hr\SubjectTeacher;
use App\Models\Branch;
use App\Models\Academic\Subject;
use App\Models\Academic\ClassConfig;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class HrSeeder extends Seeder
{
    /**
     * Seed sample HR/employee data for development/testing.
     * Creates realistic employees with designations, departments, and subject assignments.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $this->command->info('Seeding HR data...');

        // Get required data
        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations first.');
            return;
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding HR data for branch: {$branch->name}");

            // Create designations
            $this->createDesignations($branch, $faker);

            // Create HR sections (departments)
            $this->createHrSections($branch, $faker);

            // Refresh the data
            $designations = Designation::where('branch_id', $branch->id)->get();
            $hrSections = HrSection::where('branch_id', $branch->id)->get();
            $subjects = Subject::where('branch_id', $branch->id)->get();
            $classConfigs = ClassConfig::where('branch_id', $branch->id)->get();

            // Create employees
            $this->createEmployees($branch, $designations, $hrSections, $faker);

            // Create subject teacher assignments
            $this->createSubjectTeachers($branch, $subjects, $classConfigs, $faker);
        }

        $this->command->info('HR data seeded successfully.');
    }

    /**
     * Create designations for a branch
     */
    private function createDesignations(Branch $branch, $faker): void
    {
        $designations = [
            ['name' => 'Principal', 'name_bn' => 'অধ্যক্ষ', 'serial' => 1, 'description' => 'Head of the institution'],
            ['name' => 'Vice Principal', 'name_bn' => 'সহকার্য অধ্যক্ষ', 'serial' => 2, 'description' => 'Deputy head'],
            ['name' => 'Professor', 'name_bn' => 'অধ্যাপক', 'serial' => 3, 'description' => 'Senior teacher'],
            ['name' => 'Assistant Professor', 'name_bn' => 'সহকার্য অধ্যাপক', 'serial' => 4, 'description' => 'Intermediate teacher'],
            ['name' => 'Lecturer', 'name_bn' => 'প্রভাষক', 'serial' => 5, 'description' => 'Junior teacher'],
            ['name' => 'Senior Lecturer', 'name_bn' => 'জ্যেষ্ঠ প্রভাষক', 'serial' => 6, 'description' => 'Experienced lecturer'],
            ['name' => 'Administrative Officer', 'name_bn' => 'প্রশাসনিক কর্মকর্তা', 'serial' => 7, 'description' => 'Admin staff'],
            ['name' => 'Clerk', 'name_bn' => 'লিপিকার', 'serial' => 8, 'description' => 'Office clerk'],
            ['name' => 'Assistant', 'name_bn' => 'সহকারী', 'serial' => 9, 'description' => 'Office assistant'],
            ['name' => 'Peon', 'name_bn' => 'পিয়ন', 'serial' => 10, 'description' => 'Support staff'],
            ['name' => 'Security Guard', 'name_bn' => 'নিরাপত্তা প্রহরী', 'serial' => 11, 'description' => 'Security staff'],
            ['name' => 'Librarian', 'name_bn' => 'গ্রন্থাগারিক', 'serial' => 12, 'description' => 'Library manager'],
            ['name' => 'Lab Assistant', 'name_bn' => 'ল্যাব সহকারী', 'serial' => 13, 'description' => 'Laboratory assistant'],
        ];

        foreach ($designations as $designation) {
            Designation::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'name' => $designation['name'],
                ],
                [
                    'name_bn' => $designation['name_bn'],
                    'serial' => $designation['serial'],
                    'status' => true,
                    'description' => $designation['description'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }

        $this->command->info("Created " . count($designations) . " designations");
    }

    /**
     * Create HR sections (departments) for a branch
     */
    private function createHrSections(Branch $branch, $faker): void
    {
        $sections = [
            ['name' => 'Administration', 'name_bn' => 'প্রশাসন', 'serial' => 1, 'description' => 'Administrative department'],
            ['name' => 'Academic', 'name_bn' => 'একাডেমিক', 'serial' => 2, 'description' => 'Academic affairs'],
            ['name' => 'Science Department', 'name_bn' => 'বিজ্ঞান বিভাগ', 'serial' => 3, 'description' => 'Science faculty'],
            ['name' => 'Arts Department', 'name_bn' => 'কলা বিভাগ', 'serial' => 4, 'description' => 'Arts faculty'],
            ['name' => 'Commerce Department', 'name_bn' => 'বাণিজ্য বিভাগ', 'serial' => 5, 'description' => 'Commerce faculty'],
            ['name' => 'Accounts', 'name_bn' => 'হিসাব', 'serial' => 6, 'description' => 'Finance department'],
            ['name' => 'Library', 'name_bn' => 'গ্রন্থাগার', 'serial' => 7, 'description' => 'Library services'],
            ['name' => 'Laboratory', 'name_bn' => 'ল্যাবরেটরি', 'serial' => 8, 'description' => 'Science labs'],
            ['name' => 'Sports', 'name_bn' => 'ক্রীড়া', 'serial' => 9, 'description' => 'Physical education'],
            ['name' => 'Maintenance', 'name_bn' => 'রক্ষণাবেক্ষণ', 'serial' => 10, 'description' => 'Facilities maintenance'],
        ];

        foreach ($sections as $section) {
            HrSection::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'name' => $section['name'],
                ],
                [
                    'name_bn' => $section['name_bn'],
                    'serial' => $section['serial'],
                    'status' => true,
                    'description' => $section['description'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }

        $this->command->info("Created " . count($sections) . " HR sections");
    }

    /**
     * Create employees for a branch
     */
    private function createEmployees(Branch $branch, $designations, $hrSections, $faker): void
    {
        $teachingDesignations = $designations->filter(fn($d) => in_array($d->serial, [3, 4, 5, 6])); // Professor to Lecturer
        $adminDesignations = $designations->filter(fn($d) => in_array($d->serial, [1, 2, 7, 8, 9])); // Principal to Assistant
        $supportDesignations = $designations->filter(fn($d) => in_array($d->serial, [10, 11, 12, 13])); // Support staff

        $employeeCount = 30; // Total employees to create

        for ($i = 0; $i < $employeeCount; $i++) {
            // Determine if this is a teaching or non-teaching staff
            $isTeacher = $i < 20; // First 20 are teachers

            if ($isTeacher && $teachingDesignations->isNotEmpty()) {
                $designation = $teachingDesignations->random();
                $hrSection = $hrSections->filter(fn($s) => in_array($s->serial, [3, 4, 5]))->random() ?? null;
            } elseif ($adminDesignations->isNotEmpty()) {
                $designation = $i < 2 ? $adminDesignations->first() : $adminDesignations->random();
                $hrSection = $hrSections->filter(fn($s) => in_array($s->serial, [1, 2]))->random() ?? null;
            } else {
                $designation = $supportDesignations->random();
                $hrSection = $hrSections->filter(fn($s) => !in_array($s->serial, [1, 2, 3, 4, 5]))->random() ?? null;
            }

            $gender = $faker->randomElement(['male', 'female']);
            $employmentType = $faker->randomElement(['permanent', 'contract', 'temporary']);

            $employee = Employee::create([
                'branch_id' => $branch->id,
                'employee_uid' => $this->generateEmployeeUid($branch->id, $designation->id, $i + 1),
                'name' => $faker->name($gender === 'male' ? 'male' : 'female'),
                'name_bn' => $this->generateBanglaName($gender, $faker),
                'designation_id' => $designation->id,
                'hr_section_id' => $hrSection?->id,
                'sex' => $gender,
                'religion' => $faker->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
                'blood_group' => $faker->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
                'dob' => $faker->dateTimeBetween('1970-01-01', '1995-12-31')->format('Y-m-d'),
                'mobile' => $faker->phoneNumber(),
                'email' => $faker->optional()->email(),
                'nid' => $faker->numerify('################'),
                'photo_path' => null,
                'present_address' => $faker->address(),
                'permanent_address' => $faker->address(),
                'joining_date' => $faker->dateTimeBetween('2020-01-01', '2024-12-31')->format('Y-m-d'),
                'leaving_date' => null,
                'employment_type' => $employmentType,
                'status' => 'active',
                'qualifications' => $faker->randomElement([
                    ['B.Sc. in Physics', 'M.Sc. in Physics'],
                    ['B.A. in English', 'M.A. in English'],
                    ['B.Com. in Accounting', 'M.Com. in Finance'],
                    ['B.Sc. in Chemistry', 'M.Sc. in Chemistry'],
                    ['B.Sc. in Mathematics', 'M.Sc. in Mathematics'],
                    ['Bachelor Degree', 'Master Degree'],
                ]),
                'created_by' => 1,
                'updated_by' => 1,
            ]);

            // Create some employees with different statuses
            if ($i >= 25) {
                $statuses = ['resigned', 'terminated', 'retired'];
                $status = $statuses[$i - 25];

                $employee->update([
                    'status' => $status,
                    'leaving_date' => $faker->dateTimeBetween('2023-01-01', '2024-12-31')->format('Y-m-d'),
                ]);
            }
        }

        $this->command->info("Created {$employeeCount} employees");
    }

    /**
     * Create subject teacher assignments
     */
    private function createSubjectTeachers(Branch $branch, $subjects, $classConfigs, $faker): void
    {
        // Get teachers (employees with teaching designations)
        $teacherIds = Employee::where('branch_id', $branch->id)
            ->where('status', 'active')
            ->whereIn('designation_id', function($query) use ($branch) {
                $query->select('id')
                    ->from('designations')
                    ->where('branch_id', $branch->id)
                    ->whereIn('serial', [3, 4, 5, 6]); // Professor to Lecturer
            })
            ->pluck('id');

        if ($teacherIds->isEmpty()) {
            $this->command->warn('No teachers found for subject assignments');
            return;
        }

        $assignmentsCreated = 0;

        // Assign subjects to teachers for each class
        foreach ($classConfigs as $classConfig) {
            // Assign 3-5 subjects per class
            $subjectsToAssign = $faker->numberBetween(3, 5);

            for ($i = 0; $i < $subjectsToAssign; $i++) {
                if ($subjects->isEmpty()) break;

                $subject = $subjects->random();
                $teacherId = $teacherIds->random();

                // Check if assignment already exists
                $existing = SubjectTeacher::where('employee_id', $teacherId)
                    ->where('subject_id', $subject->id)
                    ->where('class_config_id', $classConfig->id)
                    ->first();

                if (!$existing) {
                    SubjectTeacher::create([
                        'branch_id' => $branch->id,
                        'employee_id' => $teacherId,
                        'subject_id' => $subject->id,
                        'class_config_id' => $classConfig->id,
                        'is_active' => true,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);

                    $assignmentsCreated++;
                }
            }
        }

        $this->command->info("Created {$assignmentsCreated} subject teacher assignments");
    }

    /**
     * Generate a unique employee UID
     */
    private function generateEmployeeUid(int $branchId, int $designationId, int $sequence): string
    {
        $year = date('Y');
        $branchCode = sprintf('%02d', $branchId);
        $designationCode = sprintf('%03d', $designationId);
        $sequenceCode = sprintf('%04d', $sequence);

        return "EMP-{$year}-{$branchCode}{$designationCode}-{$sequenceCode}";
    }

    /**
     * Generate a realistic Bangla name
     */
    private function generateBanglaName(string $gender, $faker): string
    {
        $maleNames = ['ড. মোহাম্মদ হাসান', 'অধ্যাপক আব্দুল্লাহ', 'ড. রাকিবুল ইসলাম', 'মো. তানভীর আহমেদ', 'ড. জুবায়ের আহমেদ'];
        $femaleNames = ['ড. ফাতেমা খানম', 'অধ্যাপক আয়শা খানম', 'ড. রেহেনা পারভীন', 'মোছাম্মৎ আক্তার', 'ড. সুমাইয়া আক্তার'];

        if ($gender === 'male') {
            return $faker->randomElement($maleNames);
        }

        return $faker->randomElement($femaleNames);
    }
}