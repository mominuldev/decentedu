<?php

namespace Tests\Feature;

use App\Jobs\ProcessAttendancePunches;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendanceDeviceMap;
use App\Models\Attendance\AttendanceTimeConfig;
use App\Models\Attendance\EmployeeAttendance;
use App\Models\Branch;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDeviceIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Employee $employee;
    private AttendanceDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $designation = Designation::create(['branch_id' => $this->branch->id, 'name' => 'Lecturer']);
        $this->employee = Employee::create([
            'branch_id' => $this->branch->id,
            'employee_uid' => 'EMP-001',
            'name' => 'Jane Teacher',
            'designation_id' => $designation->id,
            'sex' => 'female',
            'joining_date' => '2024-01-01',
            'status' => 'active',
        ]);

        $this->device = AttendanceDevice::create(['branch_id' => $this->branch->id, 'name' => 'Gate', 'device_uid' => 'DEV-1']);
        AttendanceDeviceMap::create([
            'branch_id' => $this->branch->id,
            'attendance_device_id' => $this->device->id,
            'external_user_id' => 'E001',
            'mappable_type' => 'employee',
            'mappable_id' => $this->employee->id,
        ]);

        AttendanceTimeConfig::create([
            'branch_id' => $this->branch->id,
            'applicable_to' => 'employee',
            'in_time' => '09:00',
            'out_time' => '17:00',
            'late_after' => '09:10',
        ]);
    }

    public function test_resolves_punches_into_present_when_on_time(): void
    {
        \App\Models\Attendance\AttendancePunch::create([
            'branch_id' => $this->branch->id,
            'attendance_device_id' => $this->device->id,
            'external_user_id' => 'E001',
            'punched_at' => '2026-07-20 08:55:00',
        ]);
        \App\Models\Attendance\AttendancePunch::create([
            'branch_id' => $this->branch->id,
            'attendance_device_id' => $this->device->id,
            'external_user_id' => 'E001',
            'punched_at' => '2026-07-20 17:05:00',
        ]);

        (new ProcessAttendancePunches($this->branch->id))->handle();

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->employee->id,
            'date' => '2026-07-20',
            'status' => 'present',
            'source' => 'device',
        ]);

        $attendance = EmployeeAttendance::where('employee_id', $this->employee->id)->first();
        $this->assertSame('08:55:00', $attendance->in_time);
        $this->assertSame('17:05:00', $attendance->out_time);

        $this->assertSame(0, \App\Models\Attendance\AttendancePunch::where('processed', false)->count());
    }

    public function test_resolves_punches_into_late_when_after_grace(): void
    {
        \App\Models\Attendance\AttendancePunch::create([
            'branch_id' => $this->branch->id,
            'attendance_device_id' => $this->device->id,
            'external_user_id' => 'E001',
            'punched_at' => '2026-07-20 09:20:00',
        ]);

        (new ProcessAttendancePunches($this->branch->id))->handle();

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->employee->id,
            'date' => '2026-07-20',
            'status' => 'late',
        ]);
    }

    public function test_unmapped_device_user_is_left_unprocessed(): void
    {
        \App\Models\Attendance\AttendancePunch::create([
            'branch_id' => $this->branch->id,
            'attendance_device_id' => $this->device->id,
            'external_user_id' => 'UNKNOWN',
            'punched_at' => '2026-07-20 09:00:00',
        ]);

        (new ProcessAttendancePunches($this->branch->id))->handle();

        $this->assertSame(0, EmployeeAttendance::count());
        $this->assertSame(1, \App\Models\Attendance\AttendancePunch::where('processed', false)->count());
    }
}
