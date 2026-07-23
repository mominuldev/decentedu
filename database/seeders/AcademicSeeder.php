<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Group;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Academic\Category;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\GroupConfig;
use Illuminate\Database\Seeder;

class AcademicSeeder extends Seeder
{
    /**
     * Seed the academic foundation data for a development environment.
     * Creates basic classes, shifts, sections, groups, subjects, and academic years.
     */
    public function run(): void
    {
        $this->command->info('Seeding Academic Foundation data...');

        // Get branches from the database or use a default one
        $branches = \App\Models\Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations and branches first.');
            return;
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding data for branch: {$branch->name}");

            // Academic Years
            $years = [
                ['name' => '2024-2025', 'name_bn' => '২০২৪-২০২৫', 'is_current' => false, 'serial' => 1],
                ['name' => '2025-2026', 'name_bn' => '২০২৫-২০২৬', 'is_current' => true, 'serial' => 2],
                ['name' => '2026-2027', 'name_bn' => '২০২৬-২০২৭', 'is_current' => false, 'serial' => 3],
            ];

            foreach ($years as $year) {
                AcademicYear::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $year['name'],
                    ],
                    [
                        'name_bn' => $year['name_bn'],
                        'start_date' => now()->startOfYear(),
                        'end_date' => now()->endOfYear(),
                        'is_current' => $year['is_current'],
                        'serial' => $year['serial'],
                        'status' => true,
                    ]
                );
            }

            // Classes
            $classes = [
                ['name' => 'Six', 'name_bn' => 'ষষ্ঠ', 'serial' => 6],
                ['name' => 'Seven', 'name_bn' => 'সপ্তম', 'serial' => 7],
                ['name' => 'Eight', 'name_bn' => 'অষ্টম', 'serial' => 8],
                ['name' => 'Nine', 'name_bn' => 'নবম', 'serial' => 9],
                ['name' => 'Ten', 'name_bn' => 'দশম', 'serial' => 10],
            ];

            foreach ($classes as $class) {
                SchoolClass::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $class['name'],
                    ],
                    [
                        'name_bn' => $class['name_bn'],
                        'serial' => $class['serial'],
                        'status' => true,
                    ]
                );
            }

            // Shifts
            $shifts = [
                ['name' => 'Morning', 'name_bn' => 'সকাল', 'serial' => 1],
                ['name' => 'Day', 'name_bn' => 'দিন', 'serial' => 2],
                ['name' => 'Evening', 'name_bn' => 'সন্ধ্যা', 'serial' => 3],
            ];

            foreach ($shifts as $shift) {
                Shift::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $shift['name'],
                    ],
                    [
                        'name_bn' => $shift['name_bn'],
                        'serial' => $shift['serial'],
                        'status' => true,
                    ]
                );
            }

            // Sections
            $sections = [
                ['name' => 'A', 'name_bn' => 'ক', 'serial' => 1],
                ['name' => 'B', 'name_bn' => 'খ', 'serial' => 2],
                ['name' => 'C', 'name_bn' => 'গ', 'serial' => 3],
            ];

            foreach ($sections as $section) {
                Section::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $section['name'],
                    ],
                    [
                        'name_bn' => $section['name_bn'],
                        'serial' => $section['serial'],
                        'status' => true,
                    ]
                );
            }

            // Groups (Study Streams)
            $groups = [
                ['name' => 'Science', 'name_bn' => 'বিজ্ঞান', 'serial' => 1],
                ['name' => 'Arts', 'name_bn' => 'কলা', 'serial' => 2],
                ['name' => 'Commerce', 'name_bn' => 'বাণিজ্য', 'serial' => 3],
            ];

            foreach ($groups as $group) {
                Group::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $group['name'],
                    ],
                    [
                        'name_bn' => $group['name_bn'],
                        'serial' => $group['serial'],
                        'status' => true,
                    ]
                );
            }

            // Categories
            $categories = [
                ['name' => 'General', 'name_bn' => 'সাধারণ', 'serial' => 1],
                ['name' => 'Honours', 'name_bn' => 'সম্মান', 'serial' => 2],
            ];

            foreach ($categories as $category) {
                Category::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'name' => $category['name'],
                    ],
                    [
                        'name_bn' => $category['name_bn'],
                        'serial' => $category['serial'],
                        'status' => true,
                    ]
                );
            }

            // Subjects (Core curriculum)
            $subjects = [
                ['name' => 'Mathematics', 'name_bn' => 'গণিত', 'code' => 'MATH', 'serial' => 1],
                ['name' => 'Physics', 'name_bn' => 'পদার্থবিজ্ঞান', 'code' => 'PHY', 'serial' => 2],
                ['name' => 'Chemistry', 'name_bn' => 'রসায়ন', 'code' => 'CHEM', 'serial' => 3],
                ['name' => 'Biology', 'name_bn' => 'জীববিজ্ঞান', 'code' => 'BIO', 'serial' => 4],
                ['name' => 'English', 'name_bn' => 'ইংরেজি', 'code' => 'ENG', 'serial' => 5],
                ['name' => 'Bengali', 'name_bn' => 'বাংলা', 'code' => 'BAN', 'serial' => 6],
                ['name' => 'History', 'name_bn' => 'ইতিহাস', 'code' => 'HIST', 'serial' => 7],
                ['name' => 'Geography', 'name_bn' => 'ভূগোল', 'code' => 'GEOG', 'serial' => 8],
                ['name' => 'Economics', 'name_bn' => 'অর্থনীতি', 'code' => 'ECON', 'serial' => 9],
                ['name' => 'ICT', 'name_bn' => 'তথ্য ও যোগাযোগ প্রযুক্তি', 'code' => 'ICT', 'serial' => 10],
            ];

            foreach ($subjects as $subject) {
                Subject::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'code' => $subject['code'],
                    ],
                    [
                        'name' => $subject['name'],
                        'name_bn' => $subject['name_bn'],
                        'serial' => $subject['serial'],
                        'status' => true,
                    ]
                );
            }

            // Create Class Configurations (Class × Shift × Section combinations)
            $classes = SchoolClass::where('branch_id', $branch->id)->get();
            $shifts = Shift::where('branch_id', $branch->id)->get();
            $sections = Section::where('branch_id', $branch->id)->get();

            foreach ($classes as $class) {
                foreach ($shifts as $shift) {
                    foreach ($sections as $section) {
                        ClassConfig::firstOrCreate(
                            [
                                'branch_id' => $branch->id,
                                'class_id' => $class->id,
                                'shift_id' => $shift->id,
                                'section_id' => $section->id,
                            ],
                            [
                                'name' => "{$class->name} - {$shift->name} - {$section->name}",
                                'serial' => ($class->serial * 100) + ($shift->serial * 10) + $section->serial,
                                'status' => true,
                            ]
                        );
                    }
                }
            }

            // Create Group Configurations (Class × Group combinations for higher classes)
            $higherClasses = $classes->filter(fn($c) => $c->serial >= 9); // Nine and Ten
            $groups = Group::where('branch_id', $branch->id)->get();

            foreach ($higherClasses as $class) {
                foreach ($groups as $group) {
                    GroupConfig::firstOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'class_id' => $class->id,
                            'group_id' => $group->id,
                        ],
                        [
                            'name' => "{$class->name} - {$group->name}",
                            'serial' => ($class->serial * 10) + $group->serial,
                            'status' => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('Academic Foundation data seeded successfully.');
    }
}