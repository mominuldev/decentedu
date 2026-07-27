<?php

namespace Tests\Feature;

use App\Enums\Cms\ContentStatus;
use App\Models\Branch;
use App\Models\Cms\Menu;
use App\Models\Cms\Notice;
use App\Models\Cms\Page;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The anonymous public-site API (/api/v1/site/*) that the Next.js marketing frontend
 * consumes: no auth, branch pinned by config('cms.public_branch_id'), published-only.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Public Branch', 'code' => 'PUB']);
        $this->otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTH']);

        config()->set('cms.public_branch_id', $this->branch->id);
    }

    /** Create a notice for the given branch without relying on request branch context. */
    private function makeNotice(Branch $branch, array $attributes): Notice
    {
        app(BranchContext::class)->set($branch->id);
        $notice = Notice::create($attributes);
        app(BranchContext::class)->set(null);

        return $notice;
    }

    public function test_notices_are_served_anonymously_and_published_only(): void
    {
        $this->makeNotice($this->branch, [
            'title' => 'Admission Open', 'status' => ContentStatus::Published,
            'notice_date' => '2026-07-20', 'is_important' => true,
        ]);
        $this->makeNotice($this->branch, [
            'title' => 'Secret Draft', 'status' => ContentStatus::Draft, 'notice_date' => '2026-07-21',
        ]);

        $response = $this->getJson('/api/v1/site/notices');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Admission Open', $titles);
        $this->assertNotContains('Secret Draft', $titles);
    }

    public function test_content_is_scoped_to_the_configured_branch(): void
    {
        $this->makeNotice($this->branch, [
            'title' => 'Ours', 'status' => ContentStatus::Published, 'notice_date' => '2026-07-20',
        ]);
        $this->makeNotice($this->otherBranch, [
            'title' => 'Theirs', 'status' => ContentStatus::Published, 'notice_date' => '2026-07-20',
        ]);

        $titles = collect($this->getJson('/api/v1/site/notices')->json('data'))->pluck('title')->all();

        $this->assertContains('Ours', $titles);
        $this->assertNotContains('Theirs', $titles);
    }

    public function test_page_is_resolved_by_path_with_blocks(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        $page = new Page([
            'title' => 'About Us', 'slug' => 'about',
            'template' => 'default', 'status' => ContentStatus::Published,
        ]);
        $page->forceFill(['path' => 'about'])->save();
        $page->blocks()->create(['type' => 'hero', 'payload' => ['heading' => 'Welcome'], 'position' => 0]);
        app(BranchContext::class)->set(null);

        $response = $this->getJson('/api/v1/site/pages/about');

        $response->assertOk();
        $this->assertSame('About Us', $response->json('data.title'));
        $this->assertSame('hero', $response->json('data.blocks.0.type'));
    }

    public function test_menu_is_served_by_key(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        Menu::create(['name' => 'Main Menu', 'key' => 'header', 'is_active' => true]);
        app(BranchContext::class)->set(null);

        $this->getJson('/api/v1/site/menus/header')
            ->assertOk()
            ->assertJsonPath('data.key', 'header');
    }

    public function test_requires_no_authentication(): void
    {
        $this->makeNotice($this->branch, [
            'title' => 'Public', 'status' => ContentStatus::Published, 'notice_date' => '2026-07-20',
        ]);

        // No actingAs — a guest must still get 200 (not 401).
        $this->getJson('/api/v1/site/notices')->assertOk();
    }

    public function test_returns_503_when_public_branch_not_configured(): void
    {
        config()->set('cms.public_branch_id', null);

        $this->getJson('/api/v1/site/notices')->assertStatus(503);
    }
}
