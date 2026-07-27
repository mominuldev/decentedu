<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionYear;
use App\Models\Admissions\Quota;
use App\Models\Branch;
use App\Support\BranchContext;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class AdmissionsSeeder extends Seeder
{
    /**
     * Seed a sample admission drive with quotas and applications across the pipeline
     * (pending / selected / waiting / rejected) so the Admissions screen has data to show.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $this->command?->info('Seeding Admissions data...');

        foreach (Branch::all() as $branch) {
            // BelongsToBranch auto-stamps branch_id and resolves the active branch from here.
            app(BranchContext::class)->set($branch->id);

            $classConfigs = ClassConfig::where('branch_id', $branch->id)->get();
            if ($classConfigs->isEmpty()) {
                continue;
            }

            $academicYear = AcademicYear::where('branch_id', $branch->id)
                ->where('is_current', true)
                ->first() ?? AcademicYear::where('branch_id', $branch->id)->first();

            $year = AdmissionYear::firstOrCreate(
                ['branch_id' => $branch->id, 'title' => 'Admission '.($academicYear?->name ?? now()->year)],
                [
                    'academic_year_id' => $academicYear?->id,
                    'start_date' => now()->startOfYear(),
                    'end_date' => now()->startOfYear()->addMonths(2),
                    'status' => 'open',
                    'serial' => 1,
                    'created_by' => 1,
                ],
            );

            $quotas = collect([
                ['name' => 'General', 'description' => 'Open merit seats', 'capacity' => 100, 'serial' => 1],
                ['name' => 'Freedom Fighter', 'description' => 'Children of freedom fighters', 'capacity' => 10, 'serial' => 2],
                ['name' => 'Sibling', 'description' => 'Sibling of an enrolled student', 'capacity' => 15, 'serial' => 3],
            ])->map(fn ($q) => Quota::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $q['name']],
                $q + ['status' => true, 'created_by' => 1],
            ));

            // Don't duplicate applications on re-seed.
            if (AdmissionApplication::where('admission_year_id', $year->id)->exists()) {
                continue;
            }

            $statuses = ['pending', 'pending', 'pending', 'selected', 'selected', 'waiting', 'rejected'];
            $seq = 0;

            foreach ($statuses as $status) {
                $seq++;
                $gender = $faker->randomElement(['male', 'female']);

                AdmissionApplication::create([
                    'branch_id' => $branch->id,
                    'admission_year_id' => $year->id,
                    'class_config_id' => $classConfigs->random()->id,
                    'quota_id' => $quotas->random()->id,
                    'application_no' => 'APP-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                    'name' => $faker->name($gender),
                    'sex' => $gender,
                    'religion' => $faker->randomElement(['islam', 'hindu', 'christian', 'buddhist']),
                    'blood_group' => $faker->randomElement(['A+', 'B+', 'O+', 'AB+']),
                    'dob' => $faker->dateTimeBetween('2010-01-01', '2014-12-31')->format('Y-m-d'),
                    'birth_certificate_no' => $faker->numerify('#################'),
                    'fathers_name' => $faker->name('male'),
                    'mothers_name' => $faker->name('female'),
                    'father_nid' => $faker->numerify('##########'),
                    'mother_nid' => $faker->numerify('##########'),
                    'mobile' => $faker->numerify('017########'),
                    'guardian_mobile' => $faker->numerify('018########'),
                    'present_address' => $faker->address(),
                    'score' => $faker->randomFloat(2, 40, 100),
                    'status' => $status,
                    'applied_at' => now()->subDays($faker->numberBetween(1, 30)),
                    'created_by' => 1,
                ]);
            }
        }

        app(BranchContext::class)->set(null);
        $this->command?->info('Admissions data seeded.');
    }
}
