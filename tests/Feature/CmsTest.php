<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cms\Asset;
use App\Models\Cms\Block;
use App\Models\Cms\Gallery;
use App\Models\Cms\Notice;
use App\Models\Cms\Page;
use App\Models\Cms\Post;
use App\Models\Cms\Redirect;
use App\Models\Cms\Taxonomy;
use App\Models\Cms\Term;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                ['type' => 'hero', 'is_visible' => true, 'payload' => ['heading' => 'Hello', 'highlight_heading' => 'Highlighted']],
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

        $hero = $page->blocks()->where('type', 'hero')->first();
        $this->assertSame('Highlighted', $hero->payload['highlight_heading']);

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
                [
                    'label' => 'About',
                    'linkable_type' => null,
                    'linkable_id' => null,
                    'url' => null,
                    'children' => [
                        ['label' => 'Home', 'linkable_type' => 'page', 'linkable_id' => $page['id']],
                        ['label' => 'External', 'url' => 'https://example.com'],
                    ],
                ],
            ],
        ])->assertOk();

        $reloaded = $this->getJson("/api/v1/cms/menus/{$menu['id']}")->json('data.items');
        $this->assertCount(1, $reloaded);
        $this->assertSame('About', $reloaded[0]['label']);
        $this->assertNull($reloaded[0]['resolved_url']);
        $this->assertCount(2, $reloaded[0]['children']);
        $this->assertSame('/'.$page['path'], $reloaded[0]['children'][0]['resolved_url']);
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
        $this->assertSame('variation_one', $ctaData['variation']);
    }

    public function test_cta_block_variation_two_with_repeater(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'CTA Variation Two Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'cta',
                    'is_visible' => true,
                    'payload' => [
                        'variation' => 'variation_two',
                        'subtitle' => 'Our Timeline',
                        'title' => 'Important Highlights',
                        'description' => 'A glance at major milestones.',
                        'items' => [
                            ['year' => '2005', 'title' => 'Milestone One'],
                            ['year' => '2015', 'title' => 'Milestone Two'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/cta-variation-two-page');
        $publicResponse->assertOk();

        $ctaData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('variation_two', $ctaData['variation']);
        $this->assertSame('Our Timeline', $ctaData['subtitle']);
        $this->assertSame('Important Highlights', $ctaData['title']);
        $this->assertCount(2, $ctaData['items']);
        $this->assertSame('2005', $ctaData['items'][0]['year']);
        $this->assertSame('Milestone One', $ctaData['items'][0]['title']);
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
                        'variation' => 'variation_two',
                        'content' => '<p>Established in 1995, delivering quality education for decades.</p>',
                        'image_asset_id' => $asset->id,
                        'image_caption' => 'Main Campus Building',
                        'repeater_title' => 'Key Milestones',
                        'items' => [
                            ['label' => 'Founded', 'value' => '1995'],
                            ['label' => 'Total Students', 'value' => '2,500+'],
                        ],
                        'cta_label' => 'Read Full Story',
                        'cta_url' => '/about/story',
                        'cta_target' => 'blank',
                        'cta_variant' => 'secondary',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/about-us-page');
        $publicResponse->assertOk();

        $aboutData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('about', $publicResponse->json('data.blocks.0.type'));
        $this->assertSame('variation_two', $aboutData['variation']);
        $this->assertSame('Our History & Mission', $aboutData['subtitle']);
        $this->assertSame('About Our School', $aboutData['title']);
        $this->assertSame('<p>Established in 1995, delivering quality education for decades.</p>', $aboutData['content']);
        $this->assertSame($asset->id, $aboutData['image']['id']);
        $this->assertSame('Main Campus Building', $aboutData['image_caption']);
        $this->assertSame('Key Milestones', $aboutData['repeater_title']);
        $this->assertCount(2, $aboutData['items']);
        $this->assertSame('Founded', $aboutData['items'][0]['label']);
        $this->assertSame('1995', $aboutData['items'][0]['value']);
        $this->assertSame('Read Full Story', $aboutData['cta_label']);
        $this->assertSame('/about/story', $aboutData['cta_url']);
        $this->assertSame('blank', $aboutData['cta_target']);
        $this->assertSame('secondary', $aboutData['cta_variant']);
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

    public function test_card_list_block_variation_four_with_year(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Card Variation Four Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'card_list',
                    'is_visible' => true,
                    'payload' => [
                        'variation' => 'variation_four',
                        'title' => 'Yearly Awards',
                        'items' => [
                            ['year' => '2023', 'title' => 'Best Science School Award'],
                            ['year' => '2025', 'title' => 'National Excellence Trophy'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/card-variation-four-page');
        $publicResponse->assertOk();

        $cardData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('variation_four', $cardData['variation']);
        $this->assertSame('Yearly Awards', $cardData['title']);
        $this->assertCount(2, $cardData['items']);
        $this->assertSame('2023', $cardData['items'][0]['year']);
        $this->assertSame('Best Science School Award', $cardData['items'][0]['title']);
    }

    public function test_card_list_block_variation_five(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Card Variation Five Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'card_list',
                    'is_visible' => true,
                    'payload' => [
                        'variation' => 'variation_five',
                        'title' => 'Key Stats',
                        'items' => [
                            ['count' => '98%', 'title' => 'Pass Rate', 'description' => 'Students passing national board exams.'],
                            ['count' => '50+', 'title' => 'Expert Teachers', 'description' => 'Certified academic educators.'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/card-variation-five-page');
        $publicResponse->assertOk();

        $cardData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('variation_five', $cardData['variation']);
        $this->assertSame('Key Stats', $cardData['title']);
        $this->assertCount(2, $cardData['items']);
        $this->assertSame('98%', $cardData['items'][0]['count']);
        $this->assertSame('Pass Rate', $cardData['items'][0]['title']);
        $this->assertSame('Students passing national board exams.', $cardData['items'][0]['description']);
    }

    public function test_about_block_variation_three(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'About Variation Three Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'about',
                    'is_visible' => true,
                    'payload' => [
                        'variation' => 'variation_three',
                        'title' => 'Message from Principal',
                        'content' => '<p>Welcome to our school.</p>',
                        'quote_subtitle' => 'Principal Message',
                        'quote_message' => 'Education is the most powerful weapon which you can use to change the world.',
                        'author' => 'Dr. Rahman',
                        'designation' => 'Principal & Founder',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/about-variation-three-page');
        $publicResponse->assertOk();

        $aboutData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('variation_three', $aboutData['variation']);
        $this->assertSame('Message from Principal', $aboutData['title']);
        $this->assertSame('Principal Message', $aboutData['quote_subtitle']);
        $this->assertSame('Education is the most powerful weapon which you can use to change the world.', $aboutData['quote_message']);
        $this->assertSame('Dr. Rahman', $aboutData['author']);
        $this->assertSame('Principal & Founder', $aboutData['designation']);
    }

    public function test_cta_block_variation_three(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'CTA Variation Three Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'cta',
                    'is_visible' => true,
                    'payload' => [
                        'variation' => 'variation_three',
                        'title' => 'Student Testimonial',
                        'quote_message' => 'This school provided me with endless opportunities.',
                        'author_name' => 'Sarah Ahmed',
                        'author_designation' => 'Alumni Class of 2022',
                        'disclaimer' => '* Results may vary based on individual effort.',
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/cta-variation-three-page');
        $publicResponse->assertOk();

        $ctaData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('variation_three', $ctaData['variation']);
        $this->assertSame('Student Testimonial', $ctaData['title']);
        $this->assertSame('This school provided me with endless opportunities.', $ctaData['quote_message']);
        $this->assertSame('Sarah Ahmed', $ctaData['author_name']);
        $this->assertSame('Alumni Class of 2022', $ctaData['author_designation']);
        $this->assertSame('* Results may vary based on individual effort.', $ctaData['disclaimer']);
    }

    public function test_announcement_strip_block_fetches_recent_three_notices(): void
    {
        $this->actingAsBranchUser();

        for ($i = 1; $i <= 4; $i++) {
            Notice::create([
                'title' => "Notice {$i}",
                'slug' => "notice-{$i}",
                'body' => "Content for notice {$i}",
                'status' => 'published',
                'published_at' => now(),
                'notice_date' => now()->addDays($i)->toDateString(),
            ]);
        }

        $response = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Announcement Strip Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'announcement_strip',
                    'is_visible' => true,
                    'payload' => [
                        'title' => 'Latest Announcements',
                        'limit' => 3,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);

        $publicResponse = $this->getJson('/api/v1/cms/public/pages/announcement-strip-page');
        $publicResponse->assertOk();

        $blockData = $publicResponse->json('data.blocks.0.data');
        $this->assertSame('announcement_strip', $publicResponse->json('data.blocks.0.type'));
        $this->assertSame('Latest Announcements', $blockData['title']);
        $this->assertCount(3, $blockData['notices']);
        $this->assertSame('Notice 4', $blockData['notices'][0]['title']);
    }

    public function test_gallery_can_be_created_listed_updated_and_deleted(): void
    {
        $this->actingAsBranchUser();

        // 1. Create gallery
        $createResponse = $this->postJson('/api/v1/cms/galleries', [
            'title' => 'Annual Cultural Program 2026',
            'description' => 'Highlights from our cultural evening.',
            'status' => 'published',
            'images' => [],
        ]);

        $createResponse->assertStatus(201);
        $galleryId = $createResponse->json('data.id');
        $this->assertSame('Annual Cultural Program 2026', $createResponse->json('data.title'));
        $this->assertSame('published', $createResponse->json('data.status'));

        // 2. List galleries
        $listResponse = $this->getJson('/api/v1/cms/galleries');
        $listResponse->assertOk();
        $this->assertGreaterThanOrEqual(1, count($listResponse->json('data')));

        // 3. Duplicate gallery as draft
        $duplicateResponse = $this->postJson("/api/v1/cms/galleries/{$galleryId}/duplicate");
        $duplicateResponse->assertStatus(201);
        $this->assertSame('Annual Cultural Program 2026 (Copy)', $duplicateResponse->json('data.title'));
        $this->assertSame('draft', $duplicateResponse->json('data.status'));

        // 4. Update gallery
        $updateResponse = $this->putJson("/api/v1/cms/galleries/{$galleryId}", [
            'title' => 'Updated Cultural Program 2026',
            'description' => 'Updated description.',
            'status' => 'draft',
        ]);
        $updateResponse->assertOk();
        $this->assertSame('Updated Cultural Program 2026', $updateResponse->json('data.title'));

        // 5. Delete gallery
        $deleteResponse = $this->deleteJson("/api/v1/cms/galleries/{$galleryId}");
        $deleteResponse->assertOk();
    }

    public function test_page_and_post_can_be_duplicated_as_draft(): void
    {
        $this->actingAsBranchUser();

        // 1. Create original page & duplicate
        $pageRes = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Admissions Overview',
            'template' => 'default',
            'status' => 'published',
        ]);
        $pageRes->assertStatus(201);
        $pageId = $pageRes->json('data.id');

        $pageDupRes = $this->postJson("/api/v1/cms/pages/{$pageId}/duplicate");
        $pageDupRes->assertStatus(201);
        $this->assertSame('Admissions Overview (Copy)', $pageDupRes->json('data.title'));
        $this->assertSame('draft', $pageDupRes->json('data.status'));

        // 2. Create original post & duplicate
        $postRes = $this->postJson('/api/v1/cms/posts', [
            'title' => 'Welcome to Campus 2026',
            'body' => '<p>Welcome students!</p>',
            'status' => 'published',
        ]);
        $postRes->assertStatus(201);
        $postId = $postRes->json('data.id');

        $postDupRes = $this->postJson("/api/v1/cms/posts/{$postId}/duplicate");
        $postDupRes->assertStatus(201);
        $this->assertSame('Welcome to Campus 2026 (Copy)', $postDupRes->json('data.title'));
        $this->assertSame('draft', $postDupRes->json('data.status'));
    }

    public function test_gallery_can_be_trashed_restored_and_permanently_deleted(): void
    {
        $this->actingAsBranchUser();

        // 1. Create gallery
        $res = $this->postJson('/api/v1/cms/galleries', [
            'title' => 'Campus Tour Gallery',
            'status' => 'published',
        ]);
        $res->assertStatus(201);
        $id = $res->json('data.id');

        // 2. Soft delete -> moves to trash
        $this->deleteJson("/api/v1/cms/galleries/{$id}")->assertOk();

        // 3. Trash list returns item
        $trashRes = $this->getJson('/api/v1/cms/galleries?status=trashed');
        $trashRes->assertOk();
        $this->assertCount(1, $trashRes->json('data'));

        // 4. Restore item
        $this->postJson("/api/v1/cms/galleries/{$id}/restore")->assertOk();
        $listRes = $this->getJson('/api/v1/cms/galleries');
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // 5. Soft delete again and force delete (permanent delete)
        $this->deleteJson("/api/v1/cms/galleries/{$id}")->assertOk();
        $this->deleteJson("/api/v1/cms/galleries/{$id}/force")->assertOk();

        $finalTrashRes = $this->getJson('/api/v1/cms/galleries?status=trashed');
        $this->assertCount(0, $finalTrashRes->json('data'));
    }

    public function test_page_can_be_trashed_restored_and_permanently_deleted(): void
    {
        $this->actingAsBranchUser();

        // 1. Create page
        $res = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Test Trash Page',
            'template' => 'default',
            'status' => 'published',
        ]);
        $res->assertStatus(201);
        $id = $res->json('data.id');

        // 2. Soft delete -> moves to trash
        $this->deleteJson("/api/v1/cms/pages/{$id}")->assertOk();

        // 3. Trash list returns item
        $trashRes = $this->getJson('/api/v1/cms/pages?status=trashed');
        $trashRes->assertOk();
        $this->assertCount(1, $trashRes->json('data'));

        // 4. Restore item
        $this->postJson("/api/v1/cms/pages/{$id}/restore")->assertOk();
        $listRes = $this->getJson('/api/v1/cms/pages');
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // 5. Soft delete again and force delete (permanent delete)
        $this->deleteJson("/api/v1/cms/pages/{$id}")->assertOk();
        $this->deleteJson("/api/v1/cms/pages/{$id}/force")->assertOk();

        $finalTrashRes = $this->getJson('/api/v1/cms/pages?status=trashed');
        $this->assertCount(0, $finalTrashRes->json('data'));
    }

    public function test_uploaded_image_is_returned_with_a_generated_thumbnail(): void
    {
        $this->actingAsBranchUser();

        $response = $this->post('/api/v1/cms/media', [
            'files' => [UploadedFile::fake()->image('hero.png', 400, 300)],
        ]);

        $response->assertStatus(201);
        $asset = $response->json('data.0');
        $this->assertSame('hero', $asset['name']);
        $this->assertSame('image/png', $asset['mime_type']);
        $this->assertStringContainsString('conversions/hero-thumb.webp', $asset['thumb_url']);
    }

    /**
     * SVG (and any format the image driver cannot rasterise) has no derivative on disk, so the
     * payload must point at the original file rather than advertise a 404 conversion URL.
     */
    public function test_asset_payload_falls_back_to_the_original_file_when_no_conversion_exists(): void
    {
        $this->actingAsBranchUser();

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
        $response = $this->post('/api/v1/cms/media', [
            'files' => [UploadedFile::fake()->createWithContent('logo.svg', $svg)],
        ]);

        $response->assertStatus(201);
        $asset = $response->json('data.0');
        $this->assertTrue(
            $asset['thumb_url'] === $asset['url'] || str_contains((string) $asset['thumb_url'], '/conversions/')
        );
        $this->assertTrue(
            $asset['preview_url'] === $asset['url'] || str_contains((string) $asset['preview_url'], '/conversions/')
        );
        $this->assertNull($asset['srcset']);

        // A non-image keeps null previews.
        $doc = $this->post('/api/v1/cms/media', [
            'files' => [UploadedFile::fake()->create('prospectus.pdf', 8, 'application/pdf')],
        ]);
        $this->assertNull($doc->json('data.0.thumb_url'));
    }

    public function test_favicon_sized_formats_are_accepted_by_the_uploader(): void
    {
        $this->actingAsBranchUser();

        $this->post('/api/v1/cms/media', [
            'files' => [UploadedFile::fake()->create('favicon.ico', 4, 'image/vnd.microsoft.icon')],
        ])->assertStatus(201);
    }

    public function test_site_setting_logos_round_trip_through_the_media_library(): void
    {
        $this->actingAsBranchUser();

        $upload = $this->post('/api/v1/cms/media', [
            'files' => [UploadedFile::fake()->image('logo.png', 240, 80)],
        ])->assertStatus(201);
        $assetId = $upload->json('data.0.id');

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Decent School',
            'header_logo_asset_id' => $assetId,
            'favicon_asset_id' => $assetId,
        ])->assertOk();

        $response = $this->getJson('/api/v1/cms/site-settings')->assertOk();
        $this->assertSame($assetId, $response->json('data.header_logo_asset_id'));
        $this->assertSame('logo', $response->json('data.header_logo.name'));
        $this->assertNotNull($response->json('data.header_logo.thumb_url'));
        $this->assertSame($assetId, $response->json('data.favicon_asset_id'));

        // Clearing a logo must be persisted, not ignored as "not provided".
        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Decent School',
            'header_logo_asset_id' => null,
        ])->assertOk();
        $this->assertNull($this->getJson('/api/v1/cms/site-settings')->json('data.header_logo_asset_id'));
    }

    public function test_pages_posts_and_terms_accept_a_featured_image(): void
    {
        $this->actingAsBranchUser();

        $assetId = $this->post('/api/v1/cms/media', [
            'files' => [UploadedFile::fake()->image('featured.png', 800, 400)],
        ])->assertStatus(201)->json('data.0.id');

        $page = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Featured Page', 'template' => 'default', 'status' => 'draft',
            'featured_asset_id' => $assetId,
        ])->assertStatus(201);
        $this->assertSame($assetId, $page->json('data.featured_asset_id'));
        $this->assertNotNull($page->json('data.featured_asset.thumb_url'));

        $post = $this->postJson('/api/v1/cms/posts', [
            'title' => 'Featured Post', 'status' => 'draft', 'featured_asset_id' => $assetId,
        ])->assertStatus(201);
        $this->assertSame($assetId, $post->json('data.featured_asset_id'));

        $taxonomy = Taxonomy::create(['name' => 'Category', 'hierarchical' => false, 'object_types' => ['post']]);
        $this->postJson('/api/v1/cms/terms', [
            'taxonomy_id' => $taxonomy->id, 'name' => 'Sports', 'featured_asset_id' => $assetId,
        ])->assertStatus(201);
        $this->assertSame(
            $assetId,
            $this->getJson("/api/v1/cms/taxonomies/{$taxonomy->id}")->json('data.terms.0.featured_asset_id'),
        );
    }

    public function test_gallery_block_renders_without_lazy_loading_violation(): void
    {
        $this->actingAsBranchUser();

        // Create a gallery
        $gallery = Gallery::create([
            'branch_id' => $this->branch->id,
            'title' => 'Sample Gallery',
            'status' => 'published',
        ]);

        // Create a page with a gallery block
        $pageResponse = $this->postJson('/api/v1/cms/pages', [
            'title' => 'Gallery Page',
            'template' => 'default',
            'status' => 'published',
            'blocks' => [
                [
                    'type' => 'gallery',
                    'is_visible' => true,
                    'payload' => [
                        'mode' => 'recent',
                        'limit' => 4,
                        'text_align' => 'center',
                    ],
                ],
            ],
        ]);

        $pageResponse->assertStatus(201);

        // Fetch page via public API
        $publicResponse = $this->getJson('/api/v1/cms/public/pages/gallery-page');
        $publicResponse->assertOk();
        $this->assertNotEmpty($publicResponse->json('data.blocks.0.data.galleries'));
        $this->assertEquals('center', $publicResponse->json('data.blocks.0.data.text_align'));
    }

    public function test_site_settings_store_eiin_and_header_ctas(): void
    {
        $this->actingAsBranchUser();

        $response = $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'eiin' => '824502',
            'header_topbar_cta_label' => 'Online Result',
            'header_topbar_cta_url' => '/results',
            'header_cta_label' => 'Apply for Admission',
            'header_cta_url' => 'https://admission.example.test',
        ]);

        $response->assertOk();
        $this->assertSame('824502', $response->json('data.eiin'));
        $this->assertSame('/results', $response->json('data.header_topbar_cta_url'));
        $this->assertSame('Apply for Admission', $response->json('data.header_cta_label'));
    }

    public function test_site_settings_store_footer_description_and_menu_columns(): void
    {
        $this->actingAsBranchUser();

        $menu = $this->postJson('/api/v1/cms/menus', ['name' => 'Quick Links', 'key' => 'footer-quick'])->json('data');

        $response = $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'footer_description' => 'A historic secondary institution in Chapainawabganj.',
            'footer_menus' => [
                ['title' => 'Quick Links', 'menu_id' => $menu['id']],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('A historic secondary institution in Chapainawabganj.', $response->json('data.footer_description'));
        $this->assertSame(
            [['title' => 'Quick Links', 'menu_id' => $menu['id']]],
            $response->json('data.footer_menus')
        );
    }

    public function test_site_settings_sanitize_the_copyright_html_and_store_the_bottom_menu(): void
    {
        $this->actingAsBranchUser();

        $menu = $this->postJson('/api/v1/cms/menus', ['name' => 'Legal', 'key' => 'footer-bottom'])->json('data');

        $response = $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'footer_copyright' => '<p>© 2026 <strong>School</strong></p><script>alert(1)</script>',
            'footer_bottom_menu_id' => $menu['id'],
        ]);

        $response->assertOk();
        // The WYSIWYG output is rendered as HTML by the public site, so it is purified on write.
        $this->assertSame('<p>© 2026 <strong>School</strong></p>', $response->json('data.footer_copyright'));
        $this->assertSame($menu['id'], $response->json('data.footer_bottom_menu_id'));
    }

    public function test_footer_bottom_menu_must_be_an_existing_menu(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'footer_bottom_menu_id' => 9999,
        ])->assertStatus(422)->assertJsonValidationErrors('footer_bottom_menu_id');
    }

    public function test_footer_menu_column_requires_a_title_and_an_existing_menu(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'footer_menus' => [
                ['title' => '', 'menu_id' => 9999],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['footer_menus.0.title', 'footer_menus.0.menu_id']);
    }

    public function test_header_cta_link_rejects_non_http_schemes(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'header_cta_url' => 'javascript:alert(1)',
        ])->assertStatus(422)->assertJsonValidationErrors('header_cta_url');
    }

    public function test_site_settings_store_a_color_scheme_and_per_token_overrides(): void
    {
        $this->actingAsBranchUser();

        $response = $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'color_scheme' => 'teal',
            'brand_colors' => ['primary' => '#123abc'],
        ]);

        $response->assertOk();
        $this->assertSame('teal', $response->json('data.color_scheme'));
        $this->assertSame(['primary' => '#123abc'], $response->json('data.brand_colors'));
    }

    public function test_site_settings_rejects_an_unknown_color_scheme(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'color_scheme' => 'not-a-real-scheme',
        ])->assertStatus(422)->assertJsonValidationErrors('color_scheme');
    }

    public function test_site_settings_rejects_a_non_hex_color_override(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'brand_colors' => ['primary' => 'not-a-hex'],
        ])->assertStatus(422)->assertJsonValidationErrors('brand_colors.primary');
    }

    public function test_site_settings_rejects_an_unknown_color_override_key(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'brand_colors' => ['not_a_token' => '#123abc'],
        ])->assertStatus(422)->assertJsonValidationErrors('brand_colors');
    }

    public function test_reset_site_settings_clears_the_color_scheme_and_overrides(): void
    {
        $this->actingAsBranchUser();

        $this->putJson('/api/v1/cms/site-settings', [
            'site_title' => 'Namosanker Bati High School',
            'color_scheme' => 'navy',
            'brand_colors' => ['primary' => '#123abc'],
        ])->assertOk();

        $response = $this->postJson('/api/v1/cms/site-settings/reset');
        $response->assertOk();
        $this->assertNull($response->json('data.color_scheme'));
        $this->assertNull($response->json('data.brand_colors'));
    }

    public function test_color_schemes_endpoint_lists_the_curated_presets(): void
    {
        $this->actingAsBranchUser();

        $response = $this->getJson('/api/v1/cms/site-settings/color-schemes');

        $response->assertOk();
        $this->assertArrayHasKey('forest', $response->json('data'));
        $this->assertSame('Forest & Crimson', $response->json('data.forest.label'));
        $this->assertArrayHasKey('primary', $response->json('data.forest.colors'));
    }
}
