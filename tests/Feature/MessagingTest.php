<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Messaging\SmsBalance;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);
    }

    private function actingAsBranchUser(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->branch->users()->attach($user->id);
        $this->actingAs($user);
    }

    public function test_send_debits_balance_and_marks_messages_sent(): void
    {
        $this->actingAsBranchUser();
        SmsBalance::create(['branch_id' => $this->branch->id, 'balance' => 10]);

        $response = $this->postJson('/api/v1/messaging/send', [
            'audience_type' => 'custom_numbers',
            'message' => 'Hello there',
            'numbers' => [['phone' => '01711111111', 'name' => 'Guardian 1'], ['phone' => '01722222222', 'name' => 'Guardian 2']],
        ]);

        $response->assertStatus(201);
        $batchId = $response->json('data.id');
        $this->assertDatabaseHas('sms_batches', ['id' => $batchId, 'total_recipients' => 2, 'sent_count' => 2, 'status' => 'completed']);
        $this->assertDatabaseHas('sms_messages', ['batch_id' => $batchId, 'recipient_phone' => '01711111111', 'status' => 'sent']);

        $balance = SmsBalance::where('branch_id', $this->branch->id)->first();
        $this->assertEquals(9.0, (float) $balance->balance); // 10 - (2 * 0.5 unit cost)
    }

    public function test_rejects_send_when_balance_is_insufficient(): void
    {
        $this->actingAsBranchUser();
        SmsBalance::create(['branch_id' => $this->branch->id, 'balance' => 0.5]);

        $response = $this->postJson('/api/v1/messaging/send', [
            'audience_type' => 'custom_numbers',
            'message' => 'Hello there',
            'numbers' => [['phone' => '01711111111'], ['phone' => '01722222222']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'INSUFFICIENT_SMS_BALANCE');
        $this->assertDatabaseCount('sms_batches', 0);

        $balance = SmsBalance::where('branch_id', $this->branch->id)->first();
        $this->assertEquals(0.5, (float) $balance->balance, 'Balance must not be debited on a rejected send.');
    }

    public function test_branch_isolation_for_templates(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);

        app(BranchContext::class)->set($otherBranch->id);
        \App\Models\Messaging\SmsTemplate::create(['branch_id' => $otherBranch->id, 'name' => 'Other Template', 'type' => 'general', 'message' => 'Hi']);

        app(BranchContext::class)->set($this->branch->id);
        $this->assertSame(0, \App\Models\Messaging\SmsTemplate::count(), 'Branch A must not see Branch B templates.');
    }
}
