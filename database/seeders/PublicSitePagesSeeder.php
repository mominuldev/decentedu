<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\User;
use App\Services\Cms\Cache\CmsCache;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;

/**
 * Seeds one published CMS Page for every route in the Next.js public site
 * (decentedu-fronend), mirroring its folder-based routes as a hierarchical page
 * tree, plus a "header" menu matching the frontend's primaryNav (nav-data.ts).
 *
 * Page slugs are the English URL segments the frontend routes on (about/history,
 * academics/teachers, …); titles are the Bengali labels the site displays. Runs
 * only for the branch that owns the public site, config('cms.public_branch_id').
 *
 * Idempotent: pages are matched by (branch, parent, slug); the header menu is
 * rebuilt from scratch each run so it always reflects the current nav.
 */
class PublicSitePagesSeeder extends Seeder
{
    private ?int $adminId = null;

    public function run(): void
    {
        $branchId = (int) (config('cms.public_branch_id') ?: optional(Branch::first())->id);

        if ($branchId === 0 || Branch::find($branchId) === null) {
            $this->command->warn('PublicSitePagesSeeder: no public branch found (config cms.public_branch_id). Skipping.');

            return;
        }

        $this->adminId = User::query()->min('id');
        app(BranchContext::class)->set($branchId);

        $this->command->info("Seeding public-site pages + header menu for branch {$branchId}...");

        $pages = $this->seedPages();
        $this->seedHeaderMenu($pages);

        // Seeding writes to the DB directly, bypassing the actions that normally
        // invalidate CmsCache — so flush the branch-scoped menu + page-path caches
        // (rememberMenu uses rememberForever) or the API would serve stale content.
        $cache = app(CmsCache::class);
        $cache->forgetMenu('header');
        $cache->forgetPagePaths(array_keys($pages));

        app(BranchContext::class)->set(null);

        $this->command->info('Public-site pages seeded ('.count($pages).' pages).');
    }

    /**
     * Builds the page tree. Section pages (about/academics/leadership) are the
     * dropdown containers; their children are the actual content routes.
     *
     * @return array<string, Page>  Keyed by URL path, e.g. "about/history".
     */
    private function seedPages(): array
    {
        $pages = [];

        // Top-level landing pages.
        $pages['home'] = $this->page(null, 'home', 'প্রচ্ছদ', template: 'home');
        $pages['notices'] = $this->page(null, 'notices', 'নোটিশ ও বিজ্ঞপ্তি', excerpt: 'বিদ্যালয়ের সর্বশেষ নোটিশ ও বিজ্ঞপ্তি।');
        $pages['gallery'] = $this->page(null, 'gallery', 'ছবি গ্যালারি', excerpt: 'বিদ্যালয়ের ছবি গ্যালারি।');
        $pages['contact'] = $this->page(null, 'contact', 'যোগাযোগ', excerpt: 'বিদ্যালয়ের সাথে যোগাযোগ করুন।');

        // পরিচিতি (About) section.
        $about = $this->page(null, 'about', 'পরিচিতি');
        $pages['about'] = $about;
        $pages['about/history'] = $this->page($about->id, 'history', 'সংক্ষিপ্ত ইতিহাস');
        $pages['about/info'] = $this->page($about->id, 'info', 'প্রতিষ্ঠান পরিচিতি');
        $pages['about/vision-mission'] = $this->page($about->id, 'vision-mission', 'লক্ষ্য ও উদ্দেশ্য');

        // একাডেমিক (Academics) section.
        $academics = $this->page(null, 'academics', 'একাডেমিক');
        $pages['academics'] = $academics;
        $pages['academics/students'] = $this->page($academics->id, 'students', 'শিক্ষার্থী তথ্য');
        $pages['academics/staff'] = $this->page($academics->id, 'staff', 'কর্মচারীবৃন্দ');
        $pages['academics/teachers'] = $this->page($academics->id, 'teachers', 'শিক্ষকমণ্ডলী');

        // নেতৃত্ব (Leadership) section.
        $leadership = $this->page(null, 'leadership', 'নেতৃত্ব');
        $pages['leadership'] = $leadership;
        $pages['leadership/principal'] = $this->page($leadership->id, 'principal', 'প্রধান শিক্ষকের বাণী');
        $pages['leadership/chairman'] = $this->page($leadership->id, 'chairman', 'সভাপতির বাণী');
        $pages['leadership/committee'] = $this->page($leadership->id, 'committee', 'ম্যানেজিং কমিটি');

        return $pages;
    }

    /**
     * Finds a page by its computed `path` (the canonical, branch-unique key) or
     * creates it published. Matching on path — not slug — keeps this idempotent
     * even against pages an earlier seeder created with Spatie-suffixed slugs
     * (e.g. a "home" whose slug became "home-2" but whose path is still "home").
     * Sets `path` directly (it is derived, not fillable) via computePath(), just
     * as the CreatePage action does.
     */
    private function page(?int $parentId, string $slug, string $title, string $template = 'default', ?string $excerpt = null): Page
    {
        $page = new Page([
            'title' => $title,
            'slug' => $slug,
            'parent_id' => $parentId,
            'template' => $template,
            'excerpt' => $excerpt,
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $this->adminId,
            'updated_by' => $this->adminId,
        ]);
        $page->path = $page->computePath();

        $existing = Page::query()->where('path', $page->path)->first();
        if ($existing !== null) {
            return $existing;
        }

        $page->save();

        return $page;
    }

    /**
     * Rebuilds the "header" menu to mirror the frontend primaryNav: two flat
     * links, three dropdown groups, and two more flat links. Group items carry no
     * URL; leaf items link to their Page, or to a raw URL for routes that have no
     * CMS page yet (admission, results, gallery videos).
     *
     * @param  array<string, Page>  $pages
     */
    private function seedHeaderMenu(array $pages): void
    {
        $menu = Menu::updateOrCreate(
            ['key' => 'header'],
            ['name' => 'Main Menu', 'is_active' => true],
        );
        // Clear existing items children-first: menu_items.parent_id is a
        // self-referencing cascadeOnDelete FK, and MySQL won't reliably cascade
        // rows deleted in the same bulk statement, which leaves orphans behind.
        $menu->items()->whereNotNull('parent_id')->delete();
        $menu->items()->whereNull('parent_id')->delete();

        $position = 0;

        // প্রচ্ছদ — links to the site root, not the "home" page's /home path.
        $this->link($menu, null, 'প্রচ্ছদ', $position++, url: '/');

        // পরিচিতি dropdown (about + leadership, matching the frontend grouping).
        $about = $this->group($menu, 'পরিচিতি', $position++);
        $childPos = 0;
        $this->link($menu, $about, 'সংক্ষিপ্ত ইতিহাস', $childPos++, page: $pages['about/history']);
        $this->link($menu, $about, 'প্রতিষ্ঠান পরিচিতি', $childPos++, page: $pages['about/info']);
        $this->link($menu, $about, 'লক্ষ্য ও উদ্দেশ্য', $childPos++, page: $pages['about/vision-mission']);
        $this->link($menu, $about, 'প্রধান শিক্ষকের বাণী', $childPos++, page: $pages['leadership/principal']);
        $this->link($menu, $about, 'সভাপতির বাণী', $childPos++, page: $pages['leadership/chairman']);
        $this->link($menu, $about, 'ম্যানেজিং কমিটি', $childPos++, page: $pages['leadership/committee']);

        // একাডেমিক dropdown.
        $academics = $this->group($menu, 'একাডেমিক', $position++);
        $childPos = 0;
        $this->link($menu, $academics, 'শিক্ষার্থী তথ্য', $childPos++, page: $pages['academics/students']);
        $this->link($menu, $academics, 'কর্মচারীবৃন্দ', $childPos++, page: $pages['academics/staff']);
        $this->link($menu, $academics, 'নোটিশ বোর্ড', $childPos++, page: $pages['notices']);
        $this->link($menu, $academics, 'পরীক্ষার ফলাফল', $childPos++, url: '/results');

        // ভর্তি — no CMS page yet.
        $this->link($menu, null, 'ভর্তি', $position++, url: '/academics/admission');

        // শিক্ষকমণ্ডলী.
        $this->link($menu, null, 'শিক্ষকমণ্ডলী', $position++, page: $pages['academics/teachers']);

        // গ্যালারি dropdown.
        $gallery = $this->group($menu, 'গ্যালারি', $position++);
        $childPos = 0;
        $this->link($menu, $gallery, 'ছবি', $childPos++, page: $pages['gallery']);
        $this->link($menu, $gallery, 'ভিডিও', $childPos++, url: '/gallery/videos');

        // যোগাযোগ.
        $this->link($menu, null, 'যোগাযোগ', $position++, page: $pages['contact']);
    }

    /** Creates a linkless dropdown-parent item and returns its id. */
    private function group(Menu $menu, string $label, int $position): int
    {
        return $menu->items()->create([
            'label' => $label,
            'position' => $position,
            'is_visible' => true,
        ])->id;
    }

    /** Creates a leaf item linking to a Page or a raw URL. */
    private function link(Menu $menu, ?int $parentId, string $label, int $position, ?Page $page = null, ?string $url = null): void
    {
        $menu->items()->create([
            'parent_id' => $parentId,
            'label' => $label,
            'linkable_type' => $page ? 'page' : null,
            'linkable_id' => $page?->id,
            'url' => $url,
            'position' => $position,
            'is_visible' => true,
        ]);
    }
}
