<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
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
        'contact_email',
        'contact_phone',
        'contact_address',
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
            ['branch_id' => app(\App\Support\BranchContext::class)->idOrFail()],
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
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_address' => $this->contact_address,
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
}