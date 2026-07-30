<?php

namespace Tests\Feature;

use App\Enums\Cms\ContentStatus;
use App\Models\Branch;
use App\Models\Cms\Asset;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\Notice;
use App\Models\Cms\Page;
use App\Models\Cms\SiteSetting;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_site_settings_expose_branding_for_the_configured_branch(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        $favicon = Asset::create(['name' => 'favicon.png', 'alt_text' => 'School crest', 'category' => 'cms']);
        $favicon->addMedia(UploadedFile::fake()->image('favicon.png', 64, 64))
            ->toMediaCollection(Asset::COLLECTION);
        SiteSetting::create([
            'site_title' => 'Namosanker Bati High School',
            'site_tagline' => 'Since 1927',
            'favicon_asset_id' => $favicon->id,
        ]);
        app(BranchContext::class)->set(null);

        $response = $this->getJson('/api/v1/site/settings');

        $response->assertOk();
        $this->assertSame('Namosanker Bati High School', $response->json('data.site_title'));
        $this->assertSame('Since 1927', $response->json('data.site_tagline'));
        $this->assertSame($favicon->id, $response->json('data.favicon.id'));
        $this->assertSame('School crest', $response->json('data.favicon.alt'));
        $this->assertSame('image/png', $response->json('data.favicon.mime_type'));
        $this->assertStringStartsWith('http', (string) $response->json('data.favicon.url'));
        // Internal bookkeeping stays out of the public payload.
        $response->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.favicon_asset_id');
    }

    public function test_site_settings_expose_eiin_and_header_ctas(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        SiteSetting::create([
            'site_title' => 'Namosanker Bati High School',
            'eiin' => '824502',
            'header_topbar_cta_label' => 'Online Result',
            'header_topbar_cta_url' => '/results',
            // Label without a URL: a button can't be rendered from half a CTA.
            'header_cta_label' => 'Apply for Admission',
        ]);
        app(BranchContext::class)->set(null);

        $response = $this->getJson('/api/v1/site/settings')->assertOk();

        $this->assertSame('824502', $response->json('data.eiin'));
        $this->assertSame(
            ['label' => 'Online Result', 'url' => '/results'],
            $response->json('data.header_topbar_cta')
        );
        $this->assertNull($response->json('data.header_cta'));
    }

    public function test_site_settings_resolve_footer_menu_columns_into_links(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        $menu = Menu::create(['name' => 'Quick Links', 'key' => 'footer-quick', 'is_active' => true]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => 'Notices', 'url' => '/notices', 'is_visible' => true, 'position' => 1,
        ]);
        $inactive = Menu::create(['name' => 'Retired', 'key' => 'footer-old', 'is_active' => false]);
        SiteSetting::create([
            'site_title' => 'Namosanker Bati High School',
            'footer_description' => 'A historic secondary institution.',
            'footer_menus' => [
                ['title' => 'Quick Links', 'menu_id' => $menu->id],
                // Points at a deactivated menu — the column must not render at all.
                ['title' => 'Retired', 'menu_id' => $inactive->id],
            ],
        ]);
        app(BranchContext::class)->set(null);

        $response = $this->getJson('/api/v1/site/settings')->assertOk();

        $this->assertSame('A historic secondary institution.', $response->json('data.footer_description'));
        $this->assertCount(1, $response->json('data.footer_menus'));
        $this->assertSame('Quick Links', $response->json('data.footer_menus.0.title'));
        $this->assertSame('Notices', $response->json('data.footer_menus.0.items.0.label'));
        $this->assertSame('/notices', $response->json('data.footer_menus.0.items.0.url'));
    }

    public function test_site_settings_resolve_the_footer_bottom_bar(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        $menu = Menu::create(['name' => 'Legal', 'key' => 'footer-bottom', 'is_active' => true]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => 'Terms and Conditions', 'url' => '/terms',
            'is_visible' => true, 'position' => 1,
        ]);
        SiteSetting::create([
            'site_title' => 'Namosanker Bati High School',
            'footer_copyright' => '<p>© 2026 <strong>Namosanker Bati High School</strong></p>',
            'footer_bottom_menu_id' => $menu->id,
        ]);
        app(BranchContext::class)->set(null);

        $response = $this->getJson('/api/v1/site/settings')->assertOk();

        $this->assertSame(
            '<p>© 2026 <strong>Namosanker Bati High School</strong></p>',
            $response->json('data.footer_copyright')
        );
        $this->assertSame('Terms and Conditions', $response->json('data.footer_bottom_menu.0.label'));
        $this->assertSame('/terms', $response->json('data.footer_bottom_menu.0.url'));
    }

    public function test_footer_bottom_menu_is_empty_when_its_menu_is_deactivated(): void
    {
        app(BranchContext::class)->set($this->branch->id);
        $menu = Menu::create(['name' => 'Legal', 'key' => 'footer-bottom', 'is_active' => false]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => 'Terms', 'url' => '/terms', 'is_visible' => true, 'position' => 1,
        ]);
        SiteSetting::create([
            'site_title' => 'Namosanker Bati High School',
            'footer_bottom_menu_id' => $menu->id,
        ]);
        app(BranchContext::class)->set(null);

        $this->getJson('/api/v1/site/settings')
            ->assertOk()
            ->assertJsonPath('data.footer_bottom_menu', []);
    }

    public function test_site_settings_return_nulls_when_the_branch_has_none(): void
    {
        $this->getJson('/api/v1/site/settings')
            ->assertOk()
            ->assertJsonPath('data.site_title', null)
            ->assertJsonPath('data.favicon', null);
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
