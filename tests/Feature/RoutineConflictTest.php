<?php

namespace Tests\Feature;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Models\Routines\Period;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineConflictTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Shift $shift;

    private Period $period;

    private Subject $subject;

    private Employee $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $this->shift = Shift::create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        $this->period = Period::create([
            'branch_id' => $this->branch->id, 'shift_id' => $this->shift->id,
            'name' => 'Period 1', 'start_time' => '09:00', 'end_time' => '09:45',
        ]);
        $this->subject = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics']);

        $designation = Designation::create(['branch_id' => $this->branch->id, 'name' => 'Lecturer']);
        $this->teacher = Employee::create([
            'branch_id' => $this->branch->id,
            'employee_uid' => 'EMP-001',
            'name' => 'Jane Teacher',
            'designation_id' => $designation->id,
            'sex' => 'female',
            'joining_date' => '2024-01-01',
            'status' => 'active',
        ]);
    }

    private function actingAsBranchUser(): void
    {
        $this->actingAsSuperAdmin($this->branch);
    }

    private function makeClassConfig(string $className, string $sectionName): ClassConfig
    {
        $class = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => $className]);
        $section = Section::create(['branch_id' => $this->branch->id, 'name' => $sectionName]);

        return ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $this->shift->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_can_create_a_class_routine_slot(): void
    {
        $this->actingAsBranchUser();
        $classConfig = $this->makeClassConfig('Six', 'A');

        $response = $this->postJson('/api/v1/routines', [
            'class_config_id' => $classConfig->id,
            'period_id' => $this->period->id,
            'day_of_week' => 0,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
            'room' => 'Room-1',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('class_routines', [
            'class_config_id' => $classConfig->id,
            'period_id' => $this->period->id,
            'day_of_week' => 0,
        ]);
    }

    public function test_rejects_a_second_subject_in_the_same_class_slot(): void
    {
        $this->actingAsBranchUser();
        $classConfig = $this->makeClassConfig('Six', 'A');

        $this->postJson('/api/v1/routines', [
            'class_config_id' => $classConfig->id,
            'period_id' => $this->period->id,
            'day_of_week' => 0,
            'subject_id' => $this->subject->id,
        ])->assertStatus(201);

        $otherSubject = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English']);

        $response = $this->postJson('/api/v1/routines', [
            'class_config_id' => $classConfig->id,
            'period_id' => $this->period->id,
            'day_of_week' => 0,
            'subject_id' => $otherSubject->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'This class already has a subject scheduled for that day and period.']);
    }

    public function test_rejects_double_booking_the_same_teacher_across_classes(): void
    {
        $this->actingAsBranchUser();
        $classA = $this->makeClassConfig('Six', 'A');
        $classB = $this->makeClassConfig('Seven', 'B');

        $this->postJson('/api/v1/routines', [
            'class_config_id' => $classA->id,
            'period_id' => $this->period->id,
            'day_of_week' => 1,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/routines', [
            'class_config_id' => $classB->id,
            'period_id' => $this->period->id,
            'day_of_week' => 1,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'This teacher is already assigned to another class at that day and period.']);
    }

    public function test_rejects_double_booking_the_same_room_across_classes(): void
    {
        $this->actingAsBranchUser();
        $classA = $this->makeClassConfig('Six', 'A');
        $classB = $this->makeClassConfig('Seven', 'B');

        $this->postJson('/api/v1/routines', [
            'class_config_id' => $classA->id,
            'period_id' => $this->period->id,
            'day_of_week' => 2,
            'subject_id' => $this->subject->id,
            'room' => 'Room-1',
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/routines', [
            'class_config_id' => $classB->id,
            'period_id' => $this->period->id,
            'day_of_week' => 2,
            'subject_id' => $this->subject->id,
            'room' => 'Room-1',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'This room is already booked for another class at that day and period.']);
    }

    public function test_allows_the_same_teacher_in_a_different_period_the_same_day(): void
    {
        $this->actingAsBranchUser();
        $classA = $this->makeClassConfig('Six', 'A');
        $classB = $this->makeClassConfig('Seven', 'B');

        $period2 = Period::create([
            'branch_id' => $this->branch->id, 'shift_id' => $this->shift->id,
            'name' => 'Period 2', 'start_time' => '09:45', 'end_time' => '10:30',
        ]);

        $this->postJson('/api/v1/routines', [
            'class_config_id' => $classA->id,
            'period_id' => $this->period->id,
            'day_of_week' => 3,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/routines', [
            'class_config_id' => $classB->id,
            'period_id' => $period2->id,
            'day_of_week' => 3,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_editing_a_slot_in_place_does_not_conflict_with_itself(): void
    {
        $this->actingAsBranchUser();
        $classConfig = $this->makeClassConfig('Six', 'A');

        $create = $this->postJson('/api/v1/routines', [
            'class_config_id' => $classConfig->id,
            'period_id' => $this->period->id,
            'day_of_week' => 4,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
            'room' => 'Room-1',
        ]);
        $id = $create->json('data.id');

        $response = $this->putJson("/api/v1/routines/{$id}", [
            'class_config_id' => $classConfig->id,
            'period_id' => $this->period->id,
            'day_of_week' => 4,
            'subject_id' => $this->subject->id,
            'employee_id' => $this->teacher->id,
            'room' => 'Room-1',
            'status' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', false);
    }

    public function test_branch_isolation_for_periods(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);
        $otherShift = Shift::create(['branch_id' => $otherBranch->id, 'name' => 'Evening']);

        app(BranchContext::class)->set($otherBranch->id);
        Period::create([
            'branch_id' => $otherBranch->id, 'shift_id' => $otherShift->id,
            'name' => 'Period 1', 'start_time' => '09:00', 'end_time' => '09:45',
        ]);

        app(BranchContext::class)->set($this->branch->id);
        $this->assertSame(1, Period::count(), 'Branch A must not see Branch B periods.');
    }
}
