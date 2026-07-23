<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Branch;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchA;
    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branchA = Branch::create(['organization_id' => $org->id, 'name' => 'Branch A', 'code' => 'A']);
        $this->branchB = Branch::create(['organization_id' => $org->id, 'name' => 'Branch B', 'code' => 'B']);
    }

    private function useBranch(Branch $b): void
    {
        app(BranchContext::class)->set($b->id);
    }

    public function test_creates_are_stamped_with_the_active_branch(): void
    {
        $this->useBranch($this->branchA);
        $class = SchoolClass::create(['name' => 'Six']);

        $this->assertSame($this->branchA->id, $class->branch_id);
    }

    public function test_queries_only_return_rows_for_the_active_branch(): void
    {
        $this->useBranch($this->branchA);
        SchoolClass::create(['name' => 'Six']);
        SchoolClass::create(['name' => 'Seven']);

        $this->useBranch($this->branchB);
        $this->assertSame(0, SchoolClass::count(), 'Branch B must not see Branch A rows.');

        SchoolClass::create(['name' => 'College Only']);
        $this->assertSame(1, SchoolClass::count());

        $this->useBranch($this->branchA);
        $names = SchoolClass::pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Six', 'Seven'], $names);
        $this->assertNotContains('College Only', $names);
    }

    public function test_cross_branch_record_lookup_returns_not_found(): void
    {
        $this->useBranch($this->branchB);
        $foreign = SchoolClass::create(['name' => 'College Only']);

        $this->useBranch($this->branchA);
        $this->expectException(ModelNotFoundException::class);
        SchoolClass::findOrFail($foreign->id);
    }

    public function test_without_branch_scope_sees_all_branches(): void
    {
        $this->useBranch($this->branchA);
        SchoolClass::create(['name' => 'Six']);
        $this->useBranch($this->branchB);
        SchoolClass::create(['name' => 'College Only']);

        $this->assertSame(2, SchoolClass::withoutBranchScope()->count());
    }
}
