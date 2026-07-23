<?php

namespace Database\Seeders;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceDeviceMap;
use App\Models\Attendance\AttendanceTimeConfig;
use App\Models\Attendance\EmployeeAttendance;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\StudentAttendance;
use App\Models\Branch;
use App\Models\Hr\Employee;
use App\Models\Routines\ClassRoutine;
use App\Models\Routines\Period;
use App\Models\Students\Enrollment;
use Illuminate\Database\Seeder;

class RoutineAttendanceSeeder extends Seeder
{
    /**
     * Seed sample routines + attendance data for development/testing:
     * periods, a class routine grid, devices/device maps/time configs/holidays,
     * and a week of student + employee attendance.
     */
    public function run(): void
    {
        $this->command->info('Seeding Routines & Attendance data...');

        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations first.');

            return;
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding routines & attendance for branch: {$branch->name}");

            $periods = $this->createPeriods($branch);
            $this->createClassRoutines($branch, $periods);
            $this->createHolidays($branch);
            $devices = $this->createDevices($branch);
            $this->createTimeConfigs($branch);
            $this->createDeviceMaps($branch, $devices);
            $this->createAttendanceHistory($branch);
        }

        $this->command->info('Routines & Attendance data seeded successfully.');
    }

    private function createPeriods(Branch $branch): \Illuminate\Support\Collection
    {
        $slots = [
            ['name' => 'Period 1', 'start' => '09:00', 'end' => '09:45'],
            ['name' => 'Period 2', 'start' => '09:45', 'end' => '10:30'],
            ['name' => 'Period 3', 'start' => '10:30', 'end' => '11:15'],
            ['name' => 'Period 4', 'start' => '11:30', 'end' => '12:15'],
            ['name' => 'Period 5', 'start' => '12:15', 'end' => '13:00'],
            ['name' => 'Period 6', 'start' => '13:30', 'end' => '14:15'],
        ];

        $shifts = \App\Models\Academic\Shift::where('branch_id', $branch->id)->get();
        $created = collect();

        foreach ($shifts as $shift) {
            foreach ($slots as $i => $slot) {
                $period = Period::firstOrCreate(
                    ['branch_id' => $branch->id, 'shift_id' => $shift->id, 'name' => $slot['name']],
                    ['start_time' => $slot['start'], 'end_time' => $slot['end'], 'serial' => $i + 1, 'created_by' => 1, 'updated_by' => 1],
                );
                $created->push($period);
            }
        }

        $this->command->info("Created {$created->count()} periods across {$shifts->count()} shifts");

        return $created;
    }

    private function createClassRoutines(Branch $branch, \Illuminate\Support\Collection $periods): void
    {
        $classConfigs = ClassConfig::where('branch_id', $branch->id)->get();
        $subjects = Subject::where('branch_id', $branch->id)->get();
        $teacherIds = Employee::where('branch_id', $branch->id)->where('status', 'active')->whereHas('subjectTeachers')->pluck('id');
        if ($teacherIds->isEmpty()) {
            $teacherIds = Employee::where('branch_id', $branch->id)->where('status', 'active')->pluck('id');
        }

        if ($classConfigs->isEmpty() || $subjects->isEmpty() || $teacherIds->isEmpty()) {
            $this->command->warn('Skipping class routines — missing class configs, subjects or employees.');

            return;
        }

        // Bangladesh school week: Sat(6), Sun(0)..Thu(4) are working days; Friday(5) is off.
        $workingDays = [6, 0, 1, 2, 3, 4];
        $created = 0;

        foreach ($classConfigs as $classConfig) {
            $classPeriods = $periods->where('shift_id', $classConfig->shift_id)->values();
            if ($classPeriods->isEmpty()) {
                continue;
            }

            foreach ($workingDays as $day) {
                foreach ($classPeriods as $i => $period) {
                    $exists = ClassRoutine::where('branch_id', $branch->id)
                        ->where('class_config_id', $classConfig->id)
                        ->where('day_of_week', $day)
                        ->where('period_id', $period->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    ClassRoutine::create([
                        'branch_id' => $branch->id,
                        'class_config_id' => $classConfig->id,
                        'period_id' => $period->id,
                        'day_of_week' => $day,
                        'subject_id' => $subjects[$i % $subjects->count()]->id,
                        'employee_id' => $teacherIds[$i % $teacherIds->count()],
                        'room' => $classConfig->schoolClass?->name.'-'.$classConfig->section?->name,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);
                    $created++;
                }
            }
        }

        $this->command->info("Created {$created} class routine slots");
    }

    private function createHolidays(Branch $branch): void
    {
        $year = (int) date('Y');
        $holidays = [
            ["{$year}-02-21", 'International Mother Language Day', 'শহীদ দিবস'],
            ["{$year}-03-26", 'Independence Day', 'স্বাধীনতা দিবস'],
            ["{$year}-05-01", 'May Day', 'মে দিবস'],
            ["{$year}-12-16", 'Victory Day', 'বিজয় দিবস'],
        ];

        foreach ($holidays as [$date, $title, $titleBn]) {
            Holiday::firstOrCreate(
                ['branch_id' => $branch->id, 'date' => $date],
                ['title' => $title, 'name_bn' => $titleBn, 'type' => 'public', 'created_by' => 1, 'updated_by' => 1],
            );
        }

        $this->command->info('Created '.count($holidays).' holidays');
    }

    private function createDevices(Branch $branch): \Illuminate\Support\Collection
    {
        $devices = [
            ['name' => 'Main Gate — Student Scanner', 'device_uid' => 'DEV-'.$branch->id.'-STU'],
            ['name' => 'Staff Room — Biometric', 'device_uid' => 'DEV-'.$branch->id.'-EMP'],
        ];

        return collect($devices)->map(fn (array $d) => AttendanceDevice::firstOrCreate(
            ['branch_id' => $branch->id, 'device_uid' => $d['device_uid']],
            ['name' => $d['name'], 'protocol' => 'zkteco', 'created_by' => 1, 'updated_by' => 1],
        ));
    }

    private function createTimeConfigs(Branch $branch): void
    {
        AttendanceTimeConfig::firstOrCreate(
            ['branch_id' => $branch->id, 'applicable_to' => 'student', 'class_config_id' => null],
            ['in_time' => '09:00', 'out_time' => '16:00', 'late_after' => '09:10', 'created_by' => 1, 'updated_by' => 1],
        );

        AttendanceTimeConfig::firstOrCreate(
            ['branch_id' => $branch->id, 'applicable_to' => 'employee', 'class_config_id' => null],
            ['in_time' => '08:45', 'out_time' => '16:30', 'late_after' => '09:00', 'created_by' => 1, 'updated_by' => 1],
        );

        $this->command->info('Created default student + employee time configs');
    }

    private function createDeviceMaps(Branch $branch, \Illuminate\Support\Collection $devices): void
    {
        $studentDevice = $devices->firstWhere('device_uid', 'DEV-'.$branch->id.'-STU');
        $employeeDevice = $devices->firstWhere('device_uid', 'DEV-'.$branch->id.'-EMP');

        $students = Enrollment::where('branch_id', $branch->id)->current()->with('student')->limit(10)->get();
        $employees = Employee::where('branch_id', $branch->id)->where('status', 'active')->limit(10)->get();

        $count = 0;

        foreach ($students as $i => $enrollment) {
            if (! $enrollment->student) {
                continue;
            }
            AttendanceDeviceMap::firstOrCreate(
                ['branch_id' => $branch->id, 'attendance_device_id' => $studentDevice->id, 'external_user_id' => 'S'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                ['mappable_type' => 'student', 'mappable_id' => $enrollment->student_id, 'created_by' => 1],
            );
            $count++;
        }

        foreach ($employees as $i => $employee) {
            AttendanceDeviceMap::firstOrCreate(
                ['branch_id' => $branch->id, 'attendance_device_id' => $employeeDevice->id, 'external_user_id' => 'E'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                ['mappable_type' => 'employee', 'mappable_id' => $employee->id, 'created_by' => 1],
            );
            $count++;
        }

        $this->command->info("Created {$count} device mappings");
    }

    private function createAttendanceHistory(Branch $branch): void
    {
        $enrollments = Enrollment::where('branch_id', $branch->id)->current()->get();
        $employees = Employee::where('branch_id', $branch->id)->where('status', 'active')->get();

        $studentDays = 0;
        $employeeDays = 0;

        for ($d = 1; $d <= 5; $d++) {
            $date = now()->subWeekdays($d)->toDateString();

            foreach ($enrollments as $enrollment) {
                $status = collect(['present', 'present', 'present', 'present', 'late', 'absent'])->random();
                StudentAttendance::firstOrCreate(
                    ['branch_id' => $branch->id, 'student_id' => $enrollment->student_id, 'date' => $date],
                    [
                        'enrollment_id' => $enrollment->id,
                        'class_config_id' => $enrollment->class_config_id,
                        'status' => $status,
                        'in_time' => $status === 'absent' ? null : '09:0'.rand(0, 9).':00',
                        'source' => 'manual',
                    ],
                );
                $studentDays++;
            }

            foreach ($employees as $employee) {
                $status = collect(['present', 'present', 'present', 'present', 'late', 'leave'])->random();
                EmployeeAttendance::firstOrCreate(
                    ['branch_id' => $branch->id, 'employee_id' => $employee->id, 'date' => $date],
                    [
                        'status' => $status,
                        'in_time' => $status === 'leave' ? null : '08:5'.rand(0, 9).':00',
                        'source' => 'manual',
                    ],
                );
                $employeeDays++;
            }
        }

        $this->command->info("Created {$studentDays} student-attendance and {$employeeDays} employee-attendance records");
    }
}
