<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cms\Asset;
use App\Models\Cms\Block;
use App\Models\Cms\Notice;
use App\Models\Cms\Page;
use App\Models\Cms\Post;
use App\Models\Cms\Redirect;
use App\Models\Cms\Taxonomy;
use App\Models\Cms\Term;
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
        $this->actingAsSuperAdmin($this->branch);
    }

    public function test_page_is_created_with_nested_section_blocks_and_computed_path(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'About Us',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                ['type' => 'hero', 'is_visible' => true, 'payload' => ['heading' => 'Hello']],
                ['type' => 'section', 'is_visible' => true, 'payload' => [
                    'tag' => 'section',
                    'blocks' => [
                        ['type' => 'rich_text', 'is_visible' => true, 'payload' => ['content' => '<p>Nested</p>']],
                    ],
                ]],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertSame('about-us', $response->json('data.path'));

        $page = Page::firstWhere('slug', 'about-us');
        $this->assertSame(2, $page->blocks()->count());

        $section = $page->blocks()->where('type', 'section')->first();
        $this->assertCount(1, $section->payload['blocks']);
        $this->assertSame('rich_text', $section->payload['blocks'][0]['type']);
    }

    public function test_sections_cannot_be_nested_inside_sections(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Bad Page',
            'template' => 'default',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'section', 'is_visible' => true, 'payload' => [
                    'tag' => 'section',
                    'blocks' => [
                        ['type' => 'section', 'is_visible' => true, 'payload' => ['tag' => 'div']],
                    ],
                ]],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_reparenting_a_page_recomputes_descendant_paths(): void
    {
        $this->actingAsBranchUser();

        $parent = $this->postJson('/api/v1/cms/pages', ['title' => 'Parent', 'template' => 'default', 'status' => 'published'])->json('data');
        $child = $this->postJson('/api/v1/cms/pages', ['title' => 'Child', 'template' => 'default', 'status' => 'published', 'parent_id' => $parent['id']])->json('data');
        $grandchild = $this->postJson('/api/v1/cms/pages', ['title' => 'Grandchild', 'template' => 'default', 'status' => 'published', 'parent_id' => $child['id']])->json('data');

        $this->assertSame('parent/child/grandchild', Page::find($grandchild['id'])->path);

        // Move the child to the root — its subtree paths must follow.
        $this->putJson("/api/v1/cms/pages/{$child['id']}", [
            'title' => 'Child', 'template' => 'default', 'status' => 'published', 'parent_id' => null,
        ])->assertOk();

        $this->assertSame('child', Page::find($child['id'])->path);
        $this->assertSame('child/grandchild', Page::find($grandchild['id'])->path);
    }

    public function test_a_page_cannot_be_nested_under_its_own_descendant(): void
    {
        $this->actingAsBranchUser();

        $parent = $this->postJson('/api/v1/cms/pages', ['title' => 'Parent', 'template' => 'default', 'status' => 'draft'])->json('data');
        $child = $this->postJson('/api/v1/cms/pages', ['title' => 'Child', 'template' => 'default', 'status' => 'draft', 'parent_id' => $parent['id']])->json('data');

        $this->putJson("/api/v1/cms/pages/{$parent['id']}", [
            'title' => 'Parent', 'template' => 'default', 'status' => 'draft', 'parent_id' => $child['id'],
        ])->assertStatus(422);
    }

    public function test_post_is_created_with_terms_tags_blocks_and_reading_time(): void
    {
        $this->actingAsBranchUser();

        $taxonomy = Taxonomy::create(['name' => 'Category', 'hierarchical' => true]);
        $term = Term::create(['taxonomy_id' => $taxonomy->id, 'name' => 'News']);

        $response = $this->postJson('/api/v1/cms/posts', [
            'title' => 'Big News',
            'status' => 'published',
            'body' => '<p>'.str_repeat('word ', 400).'</p>',
            'terms' => [$term->id],
            'tags' => ['announcement', 'campus'],
            'blocks' => [
                ['type' => 'rich_text', 'is_visible' => true, 'payload' => ['content' => '<p>Body block</p>']],
            ],
        ]);

        $response->assertStatus(201);
        $post = Post::firstWhere('slug', 'big-news');
        $this->assertSame(2, $post->reading_time);
        $this->assertEqualsCanonicalizing([$term->id], $post->terms()->pluck('terms.id')->all());
        $this->assertEqualsCanonicalizing(['announcement', 'campus'], $post->tags->pluck('name')->all());
        $this->assertSame(1, $post->blocks()->count());
    }

    public function test_taxonomy_requires_object_types_and_scopes_content_term_metadata(): void
    {
        $this->actingAsBranchUser();

        // object_types is required.
        $this->postJson('/api/v1/cms/taxonomies', ['name' => 'Global', 'hierarchical' => true])
            ->assertStatus(422)->assertJsonValidationErrors('object_types');

        // A post-scoped taxonomy and a notice-scoped one.
        $this->postJson('/api/v1/cms/taxonomies', ['name' => 'Blog Category', 'object_types' => ['post']])
            ->assertStatus(201);
        $this->postJson('/api/v1/cms/taxonomies', ['name' => 'Notice Category', 'object_types' => ['notice']])
            ->assertStatus(201);

        $blogTax = Taxonomy::firstWhere('name', 'Blog Category');
        $noticeTax = Taxonomy::firstWhere('name', 'Notice Category');
        Term::create(['taxonomy_id' => $blogTax->id, 'name' => 'Sports']);
        Term::create(['taxonomy_id' => $noticeTax->id, 'name' => 'Exam']);

        // Post editor only sees post-scoped terms.
        $postTerms = $this->getJson('/api/v1/cms/posts/meta')->json('data.terms');
        $this->assertEqualsCanonicalizing(['Sports'], array_column($postTerms, 'name'));

        // Notice editor only sees notice-scoped terms.
        $noticeTerms = $this->getJson('/api/v1/cms/notices/meta')->json('data.terms');
        $this->assertEqualsCanonicalizing(['Exam'], array_column($noticeTerms, 'name'));
    }

    public function test_taxonomy_can_be_updated_including_its_object_types(): void
    {
        $this->actingAsBranchUser();

        $taxonomy = Taxonomy::create(['name' => 'Category', 'hierarchical' => true, 'object_types' => ['post']]);

        $this->putJson("/api/v1/cms/taxonomies/{$taxonomy->id}", [
            'name' => 'Topics', 'hierarchical' => false, 'object_types' => ['post', 'event'],
        ])->assertStatus(200);

        $taxonomy->refresh();
        $this->assertSame('Topics', $taxonomy->name);
        $this->assertFalse($taxonomy->hierarchical);
        $this->assertEqualsCanonicalizing(['post', 'event'], $taxonomy->object_types);

        // object_types stays required on update.
        $this->putJson("/api/v1/cms/taxonomies/{$taxonomy->id}", ['name' => 'Topics', 'object_types' => []])
            ->assertStatus(422)->assertJsonValidationErrors('object_types');
    }

    public function test_post_body_is_sanitized_on_store(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/posts', [
            'title' => 'Admission Notice',
            'status' => 'draft',
            'body' => '<p>Welcome</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">link</a>',
        ]);

        $response->assertStatus(201);
        $body = $response->json('data.body');
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('<p>Welcome</p>', $body);
    }

    public function test_notice_is_created_with_category_and_attachment_and_sanitized_body(): void
    {
        $this->actingAsBranchUser();

        $taxonomy = Taxonomy::create(['name' => 'Notice Category', 'hierarchical' => false]);
        $term = Term::create(['taxonomy_id' => $taxonomy->id, 'name' => 'Admission']);
        $asset = Asset::create(['name' => 'admission.pdf']);

        $response = $this->postJson('/api/v1/cms/notices', [
            'title' => 'Admission Notice 2026',
            'status' => 'published',
            'notice_date' => '2026-07-28',
            'is_important' => true,
            'attachment_asset_id' => $asset->id,
            'terms' => [$term->id],
            'body' => '<p>Apply now</p><script>alert(1)</script>',
        ]);

        $response->assertStatus(201);
        $notice = Notice::firstWhere('slug', 'admission-notice-2026');
        $this->assertTrue($notice->is_important);
        $this->assertNotNull($notice->published_at, 'Publishing must stamp published_at.');
        $this->assertSame($asset->id, $notice->attachment_asset_id);
        $this->assertEqualsCanonicalizing([$term->id], $notice->terms()->pluck('terms.id')->all());
        $this->assertStringNotContainsString('<script>', (string) $notice->body);
    }

    public function test_public_notice_endpoint_orders_important_first_and_hides_drafts(): void
    {
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/cms/notices', ['title' => 'Routine notice', 'status' => 'published', 'notice_date' => '2026-07-20'])->assertStatus(201);
        $this->postJson('/api/v1/cms/notices', ['title' => 'Important notice', 'status' => 'published', 'notice_date' => '2026-07-01', 'is_important' => true])->assertStatus(201);
        $this->postJson('/api/v1/cms/notices', ['title' => 'Hidden draft', 'status' => 'draft', 'notice_date' => '2026-07-25'])->assertStatus(201);

        $data = $this->getJson('/api/v1/cms/public/notices')->assertOk()->json('data');

        $this->assertCount(2, $data, 'Draft notices must not be public.');
        $this->assertSame('Important notice', $data[0]['title'], 'Important notices sort first.');
    }

    public function test_event_end_must_not_precede_start(): void
    {
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/cms/events', [
            'title' => 'Bad Event',
            'status' => 'draft',
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at' => '2026-08-01 09:00:00',
        ])->assertStatus(422);
    }

    public function test_public_event_endpoint_can_filter_upcoming(): void
    {
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/cms/events', ['title' => 'Past Event', 'status' => 'published', 'starts_at' => now()->subWeek()->toDateTimeString()])->assertStatus(201);
        $this->postJson('/api/v1/cms/events', ['title' => 'Future Event', 'status' => 'published', 'starts_at' => now()->addWeek()->toDateTimeString()])->assertStatus(201);

        $all = $this->getJson('/api/v1/cms/public/events')->assertOk()->json('data');
        $this->assertCount(2, $all);

        $upcoming = $this->getJson('/api/v1/cms/public/events?upcoming=1')->assertOk()->json('data');
        $this->assertCount(1, $upcoming);
        $this->assertSame('Future Event', $upcoming[0]['title']);
    }

    public function test_menu_tree_round_trips(): void
    {
        $this->actingAsBranchUser();

        $page = $this->postJson('/api/v1/cms/pages', ['title' => 'Home', 'template' => 'home', 'status' => 'published'])->json('data');
        $menu = $this->postJson('/api/v1/cms/menus', ['name' => 'Header', 'key' => 'header'])->json('data');

        $this->putJson("/api/v1/cms/menus/{$menu['id']}/tree", [
            'items' => [
                ['label' => 'Home', 'linkable_type' => 'page', 'linkable_id' => $page['id'], 'children' => [
                    ['label' => 'External', 'url' => 'https://example.com'],
                ]],
            ],
        ])->assertOk();

        $reloaded = $this->getJson("/api/v1/cms/menus/{$menu['id']}")->json('data.items');
        $this->assertCount(1, $reloaded);
        $this->assertSame('Home', $reloaded[0]['label']);
        $this->assertCount(1, $reloaded[0]['children']);
        $this->assertSame('/'.$page['path'], $reloaded[0]['resolved_url']);
    }

    public function test_public_page_endpoint_returns_rendered_blocks(): void
    {
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/cms/pages', [
            'title' => 'Landing',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                ['type' => 'hero', 'is_visible' => true, 'payload' => ['heading' => 'Welcome']],
            ],
        ])->assertStatus(201);

        $response = $this->getJson('/api/v1/cms/public/pages/landing');
        $response->assertOk();
        $this->assertSame('Welcome', $response->json('data.blocks.0.data.heading'));
        $this->assertSame('hero', $response->json('data.blocks.0.type'));
    }

    public function test_public_page_endpoint_falls_back_to_redirect(): void
    {
        $this->actingAsBranchUser();

        $redirect = Redirect::create(['from_path' => 'old-path', 'to_path' => 'new-path', 'status_code' => 301]);

        $response = $this->getJson('/api/v1/cms/public/pages/old-path');
        $response->assertStatus(301);
        $this->assertSame('/new-path', $response->json('data.redirect_to'));
        $this->assertSame(1, $redirect->fresh()->hits);
    }

    public function test_unpublished_pages_are_hidden_from_the_public_endpoint(): void
    {
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/cms/pages', ['title' => 'Secret', 'template' => 'default', 'status' => 'draft'])->assertStatus(201);

        $this->getJson('/api/v1/cms/public/pages/secret')->assertStatus(404);
    }

    public function test_branch_isolation_for_pages(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);

        app(BranchContext::class)->set($otherBranch->id);
        $other = new Page(['title' => 'Other Page', 'slug' => 'other-page']);
        $other->path = 'other-page';
        $other->save();

        app(BranchContext::class)->set($this->branch->id);
        $this->assertSame(0, Page::count(), 'Main branch must not see other branch pages.');
    }

    public function test_deleting_a_page_soft_deletes_its_subtree(): void
    {
        $this->actingAsBranchUser();

        $parent = $this->postJson('/api/v1/cms/pages', ['title' => 'Parent', 'template' => 'default', 'status' => 'draft'])->json('data');
        $child = $this->postJson('/api/v1/cms/pages', ['title' => 'Child', 'template' => 'default', 'status' => 'draft', 'parent_id' => $parent['id']])->json('data');

        $this->deleteJson("/api/v1/cms/pages/{$parent['id']}")->assertOk();

        $this->assertSoftDeleted('pages', ['id' => $parent['id']]);
        $this->assertSoftDeleted('pages', ['id' => $child['id']]);
        $this->assertSame(0, Block::count());
    }

    public function test_cta_block_with_subtitle_title_description_and_ctas(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'CTA Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'cta',
                    'is_visible' => true,
                    'payload' => [
                        'subtitle' => 'Ready to get started?',
                        'title' => 'Join Our Academy Today',
                        'description' => 'Enroll now to access top-tier education.',
                        'cta_primary_label' => 'Apply Now',
                        'cta_primary_url' => '/admissions/apply',
                        'cta_primary_target' => 'blank',
                        'cta_primary_variant' => 'primary',
                        'cta_secondary_label' => 'Contact Us',
                        'cta_secondary_url' => '/contact',
                        'cta_secondary_target' => 'self',
                        'cta_secondary_variant' => 'outline',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/cta-page');
        $publicResponse->assertOk();

        $ctaData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('Ready to get started?', $ctaData['subtitle']);
        $this->assertSame('Join Our Academy Today', $ctaData['title']);
        $this->assertSame('Enroll now to access top-tier education.', $ctaData['description']);
        $this->assertSame('Apply Now', $ctaData['cta_primary_label']);
        $this->assertSame('/admissions/apply', $ctaData['cta_primary_url']);
        $this->assertSame('blank', $ctaData['cta_primary_target']);
        $this->assertSame('primary', $ctaData['cta_primary_variant']);
        $this->assertSame('Contact Us', $ctaData['cta_secondary_label']);
        $this->assertSame('/contact', $ctaData['cta_secondary_url']);
        $this->assertSame('self', $ctaData['cta_secondary_target']);
        $this->assertSame('outline', $ctaData['cta_secondary_variant']);
    }

    public function test_about_block_with_all_fields(): void
    {
        $this->actingAsBranchUser();

        $asset = Asset::create(['name' => 'campus.jpg']);

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'About Us Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'about',
                    'is_visible' => true,
                    'payload' => [
                        'subtitle' => 'Our History & Mission',
                        'title' => 'About Our School',
                        'content' => '<p>Established in 1995, delivering quality education for decades.</p>',
                        'image_asset_id' => $asset->id,
                        'image_caption' => 'Main Campus Building',
                        'repeater_title' => 'Key Milestones',
                        'items' => [
                            ['label' => 'Founded', 'value' => '1995'],
                            ['label' => 'Total Students', 'value' => '2,500+'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/about-us-page');
        $publicResponse->assertOk();

        $aboutData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('about', $publicResponse->json('data.blocks.0.type'));
        $this->assertSame('Our History & Mission', $aboutData['subtitle']);
        $this->assertSame('About Our School', $aboutData['title']);
        $this->assertSame('<p>Established in 1995, delivering quality education for decades.</p>', $aboutData['content']);
        $this->assertSame($asset->id, $aboutData['image']['id']);
        $this->assertSame('Main Campus Building', $aboutData['image_caption']);
        $this->assertSame('Key Milestones', $aboutData['repeater_title']);
        $this->assertCount(2, $aboutData['items']);
        $this->assertSame('Founded', $aboutData['items'][0]['label']);
        $this->assertSame('1995', $aboutData['items'][0]['value']);
    }

    public function test_milestones_timeline_block_with_all_fields(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Our Journey',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'milestones_timeline',
                    'is_visible' => true,
                    'payload' => [
                        'subtitle' => 'Our Growth & Achievements',
                        'title' => 'Key Milestones Over The Years',
                        'description' => 'A timeline of how our institution grew since establishment.',
                        'content_align' => 'center',
                        'items' => [
                            ['year' => '1995', 'title' => 'Founding Year', 'description' => 'Established with 50 students.'],
                            ['year' => '2010', 'title' => 'Campus Expansion', 'description' => 'Opened secondary school wing.'],
                            ['year' => '2022', 'title' => 'Digital Campus Launch', 'description' => 'Integrated smart classrooms and ERP.'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/our-journey');
        $publicResponse->assertOk();

        $timelineData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('milestones_timeline', $publicResponse->json('data.blocks.0.type'));
        $this->assertSame('Our Growth & Achievements', $timelineData['subtitle']);
        $this->assertSame('Key Milestones Over The Years', $timelineData['title']);
        $this->assertSame('A timeline of how our institution grew since establishment.', $timelineData['description']);
        $this->assertSame('center', $timelineData['content_align']);
        $this->assertCount(3, $timelineData['items']);
        $this->assertSame('1995', $timelineData['items'][0]['year']);
        $this->assertSame('Founding Year', $timelineData['items'][0]['title']);
        $this->assertSame('Established with 50 students.', $timelineData['items'][0]['description']);

        // Default content_align should be center when omitted
        $defaultResp = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Default Journey',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                ['type' => 'milestones_timeline', 'is_visible' => true, 'payload' => []],
            ],
        ]);
        $defaultResp->assertStatus(201);
        $publicDefault = $this->getJson('/api/v1/cms/public/pages/default-journey')->assertOk();
        $this->assertSame('center', $publicDefault->json('data.blocks.0.data.content_align'));
    }
}
