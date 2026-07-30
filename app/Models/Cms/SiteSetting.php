<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    use BelongsToBranch;
    use HasFactory;

    protected $table = 'cms_site_settings';

    protected $fillable = [
        'site_title',
        'site_tagline',
        'site_description',
        'header_logo_asset_id',
        'footer_logo_asset_id',
        'favicon_asset_id',
        'eiin',
        'color_scheme',
        'brand_colors',
        'header_topbar_cta_label',
        'header_topbar_cta_url',
        'header_cta_label',
        'header_cta_url',
        'contact_email',
        'contact_phone',
        'contact_address',
        'footer_description',
        'footer_menus',
        'footer_copyright',
        'footer_bottom_menu_id',
        'facebook_url',
        'twitter_url',
        'linkedin_url',
        'youtube_url',
        'instagram_url',
        'google_analytics_code',
        'google_tag_manager_code',
        'meta_keywords',
        'additional_settings',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'additional_settings' => 'array',
            'footer_menus' => 'array',
            'brand_colors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function headerLogo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'header_logo_asset_id');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function footerLogo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'footer_logo_asset_id');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function favicon(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'favicon_asset_id');
    }

    /**
     * The menu shown beside the copyright line (privacy policy, terms and conditions, …).
     *
     * @return BelongsTo<Menu, $this>
     */
    public function footerBottomMenu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'footer_bottom_menu_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get or create site settings for the current branch.
     */
    public static function forBranch(): self
    {
        return self::firstOrCreate(
            ['branch_id' => app(BranchContext::class)->idOrFail()],
            [
                'site_title' => config('app.name', 'Safe Eduman'),
                'created_by' => auth()->id(),
            ]
        );
    }

    /**
     * Convert to API payload.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        return [
            'id' => $this->id,
            'site_title' => $this->site_title,
            'site_tagline' => $this->site_tagline,
            'site_description' => $this->site_description,
            'header_logo_asset_id' => $this->header_logo_asset_id,
            'header_logo' => $this->headerLogo?->toApiPayload(),
            'footer_logo_asset_id' => $this->footer_logo_asset_id,
            'footer_logo' => $this->footerLogo?->toApiPayload(),
            'favicon_asset_id' => $this->favicon_asset_id,
            'favicon' => $this->favicon?->toApiPayload(),
            'eiin' => $this->eiin,
            'color_scheme' => $this->color_scheme,
            'brand_colors' => $this->brand_colors,
            'header_topbar_cta_label' => $this->header_topbar_cta_label,
            'header_topbar_cta_url' => $this->header_topbar_cta_url,
            'header_cta_label' => $this->header_cta_label,
            'header_cta_url' => $this->header_cta_url,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_address' => $this->contact_address,
            'footer_description' => $this->footer_description,
            'footer_menus' => $this->footer_menus ?? [],
            'footer_copyright' => $this->footer_copyright,
            'footer_bottom_menu_id' => $this->footer_bottom_menu_id,
            'facebook_url' => $this->facebook_url,
            'twitter_url' => $this->twitter_url,
            'linkedin_url' => $this->linkedin_url,
            'youtube_url' => $this->youtube_url,
            'instagram_url' => $this->instagram_url,
            'google_analytics_code' => $this->google_analytics_code,
            'google_tag_manager_code' => $this->google_tag_manager_code,
            'meta_keywords' => $this->meta_keywords,
            'additional_settings' => $this->additional_settings,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Preset colors with the admin's per-token overrides layered on top. Falls back to the
     * configured default preset when the branch has never chosen one, and drops override
     * keys that aren't real tokens so a stale payload can't inject arbitrary CSS.
     *
     * @return array<string, string>
     */
    public function resolvedThemeColors(): array
    {
        $schemes = config('cms.color_schemes', []);
        $defaultKey = config('cms.default_color_scheme', 'forest');
        $key = $this->color_scheme ?: $defaultKey;
        $base = $schemes[$key]['colors'] ?? $schemes[$defaultKey]['colors'] ?? [];
        $overrides = array_intersect_key($this->brand_colors ?? [], $base);

        return array_merge($base, $overrides);
    }
}
