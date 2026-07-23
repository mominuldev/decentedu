<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cms\Post;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsTest extends TestCase
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

    public function test_post_body_is_sanitized_on_store(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/posts', [
            'type' => 'notice',
            'title' => 'Admission Notice',
            'body' => '<p>Welcome</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">link</a>',
        ]);

        $response->assertStatus(201);
        $body = $response->json('data.body');
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('<p>Welcome</p>', $body);
    }

    public function test_duplicate_titles_get_a_unique_slug_within_the_same_type(): void
    {
        $this->actingAsBranchUser();

        $first = $this->postJson('/api/v1/cms/posts', ['type' => 'news', 'title' => 'Sports Day'])->json('data');
        $second = $this->postJson('/api/v1/cms/posts', ['type' => 'news', 'title' => 'Sports Day'])->json('data');

        $this->assertNotEquals($first['slug'], $second['slug']);
    }

    public function test_branch_isolation_for_posts(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);

        app(BranchContext::class)->set($otherBranch->id);
        Post::create(['branch_id' => $otherBranch->id, 'type' => 'page', 'title' => 'Other Page', 'slug' => 'other-page']);

        app(BranchContext::class)->set($this->branch->id);
        $this->assertSame(0, Post::count(), 'Branch A must not see Branch B posts.');
    }
}
