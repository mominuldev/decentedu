<?php

namespace Tests\Feature;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Accounting\LedgerAccount;
use App\Models\Accounting\VoucherEntry;
use App\Models\Branch;
use App\Models\Fees\FeeConfig;
use App\Models\Fees\FeeHead;
use App\Models\Fees\FeeSubHead;
use App\Models\Fees\FeeTimeConfig;
use App\Models\Fees\StudentFee;
use App\Models\Organization;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private ClassConfig $classConfig;

    private AcademicYear $year;

    private Student $student;

    private FeeSubHead $tuitionSubHead;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $class = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Six']);
        $shift = Shift::create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        $section = Section::create(['branch_id' => $this->branch->id, 'name' => 'A']);
        $this->classConfig = ClassConfig::create([
            'branch_id' => $this->branch->id, 'class_id' => $class->id, 'shift_id' => $shift->id, 'section_id' => $section->id,
        ]);
        $this->year = AcademicYear::create(['branch_id' => $this->branch->id, 'name' => '2026', 'is_current' => true]);

        $this->student = Student::create([
            'branch_id' => $this->branch->id, 'student_uid' => 'STU-A', 'name' => 'Student A',
            'sex' => 'male', 'fathers_name' => 'Father A', 'mothers_name' => 'Mother A', 'status' => 'active',
        ]);
        Enrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id, 'roll' => '1', 'is_current' => true,
        ]);

        $head = FeeHead::create(['branch_id' => $this->branch->id, 'name' => 'Tuition Fee']);
        $this->tuitionSubHead = FeeSubHead::create(['branch_id' => $this->branch->id, 'fee_head_id' => $head->id, 'name' => 'Monthly Tuition']);
        FeeConfig::create([
            'branch_id' => $this->branch->id, 'class_config_id' => $this->classConfig->id,
            'fee_sub_head_id' => $this->tuitionSubHead->id, 'academic_year_id' => $this->year->id, 'amount' => 1000,
        ]);
    }

    private function actingAsBranchUser(): void
    {
        $this->actingAsSuperAdmin($this->branch);
    }

    public function test_assess_generates_a_student_fee_from_the_config(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/fees/configs/assess', [
            'class_config_id' => $this->classConfig->id,
            'academic_year_id' => $this->year->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.student_fees_assessed', 1);
        $this->assertDatabaseHas('student_fees', [
            'student_id' => $this->student->id,
            'fee_sub_head_id' => $this->tuitionSubHead->id,
            'payable_amount' => 1000,
            'status' => 'due',
        ]);
    }

    public function test_partial_payment_leaves_the_fee_partially_paid(): void
    {
        $this->actingAsBranchUser();
        $this->postJson('/api/v1/fees/configs/assess', [
            'class_config_id' => $this->classConfig->id, 'academic_year_id' => $this->year->id,
        ])->assertStatus(200);

        $studentFee = StudentFee::where('student_id', $this->student->id)->first();

        $response = $this->postJson('/api/v1/fees/collections', [
            'student_id' => $this->student->id,
            'payment_method' => 'cash',
            'items' => [['student_fee_id' => $studentFee->id, 'amount' => 400]],
        ]);

        $response->assertStatus(201);
        $studentFee->refresh();
        $this->assertSame('partial', $studentFee->status);
        $this->assertEquals(400, $studentFee->paid_amount);
        $this->assertEquals(600, $studentFee->dueAmount());
    }

    public function test_collection_posts_a_balanced_receive_voucher_to_the_ledger(): void
    {
        $this->actingAsBranchUser();
        $this->postJson('/api/v1/fees/configs/assess', [
            'class_config_id' => $this->classConfig->id, 'academic_year_id' => $this->year->id,
        ])->assertStatus(200);
        $studentFee = StudentFee::where('student_id', $this->student->id)->first();

        $response = $this->postJson('/api/v1/fees/collections', [
            'student_id' => $this->student->id,
            'payment_method' => 'cash',
            'items' => [['student_fee_id' => $studentFee->id, 'amount' => 1000]],
        ]);

        $response->assertStatus(201);
        $voucherId = $response->json('data.voucher_id');
        $this->assertNotNull($voucherId, 'Fee collection should auto-post a voucher.');

        $this->assertDatabaseHas('vouchers', ['id' => $voucherId, 'type' => 'receive', 'total' => 1000]);

        $entries = VoucherEntry::where('voucher_id', $voucherId)->get();
        $this->assertEquals(1000, $entries->sum('debit'));
        $this->assertEquals(1000, $entries->sum('credit'));

        $cash = LedgerAccount::where('branch_id', $this->branch->id)->where('code', 'CASH')->first();
        $this->assertNotNull($cash);
        $this->assertTrue($entries->contains(fn ($e) => $e->ledger_account_id === $cash->id && (float) $e->debit === 1000.0));
    }

    public function test_flat_fine_is_charged_once_when_paying_after_the_due_date(): void
    {
        $this->actingAsBranchUser();
        FeeTimeConfig::create([
            'branch_id' => $this->branch->id, 'fee_sub_head_id' => $this->tuitionSubHead->id,
            'academic_year_id' => $this->year->id, 'due_date' => now()->subDays(10)->toDateString(), 'fine_amount' => 50,
        ]);
        $this->postJson('/api/v1/fees/configs/assess', [
            'class_config_id' => $this->classConfig->id, 'academic_year_id' => $this->year->id,
        ])->assertStatus(200);
        $studentFee = StudentFee::where('student_id', $this->student->id)->first();

        // Full payable now costs payable + fine (1050), so a 1000 payment cannot fully settle it.
        $response = $this->postJson('/api/v1/fees/collections', [
            'student_id' => $this->student->id,
            'payment_method' => 'cash',
            'items' => [['student_fee_id' => $studentFee->id, 'amount' => 1000]],
        ]);
        $response->assertStatus(201);

        $studentFee->refresh();
        $this->assertEquals(50, $studentFee->fine_amount);
        $this->assertEquals('partial', $studentFee->status);
        $this->assertEquals(50, $studentFee->dueAmount());
    }

    public function test_rejects_overpaying_beyond_the_due_amount(): void
    {
        $this->actingAsBranchUser();
        $this->postJson('/api/v1/fees/configs/assess', [
            'class_config_id' => $this->classConfig->id, 'academic_year_id' => $this->year->id,
        ])->assertStatus(200);
        $studentFee = StudentFee::where('student_id', $this->student->id)->first();

        $response = $this->postJson('/api/v1/fees/collections', [
            'student_id' => $this->student->id,
            'payment_method' => 'cash',
            'items' => [['student_fee_id' => $studentFee->id, 'amount' => 5000]],
        ]);

        $response->assertStatus(422);
    }

    public function test_voucher_store_rejects_unbalanced_entries(): void
    {
        $this->actingAsBranchUser();
        $cash = LedgerAccount::create(['branch_id' => $this->branch->id, 'name' => 'Cash', 'code' => 'CASH-T', 'type' => 'asset']);
        $expense = LedgerAccount::create(['branch_id' => $this->branch->id, 'name' => 'Utility', 'code' => 'UTIL', 'type' => 'expense']);

        $response = $this->postJson('/api/v1/accounting/vouchers', [
            'type' => 'payment',
            'date' => now()->toDateString(),
            'entries' => [
                ['ledger_account_id' => $expense->id, 'debit' => 100, 'credit' => 0],
                ['ledger_account_id' => $cash->id, 'debit' => 0, 'credit' => 90],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_branch_isolation_for_fee_heads(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);

        app(BranchContext::class)->set($otherBranch->id);
        FeeHead::create(['branch_id' => $otherBranch->id, 'name' => 'Other Fee']);

        app(BranchContext::class)->set($this->branch->id);
        $this->assertSame(1, FeeHead::count(), 'Branch A must not see Branch B fee heads.');
    }
}
