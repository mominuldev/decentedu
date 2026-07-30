<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\SiteSettings;

use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteSettingRequest extends FormRequest
{
    /**
     * A link target that is safe to render as an href: a site-relative path or an
     * absolute http(s) URL.
     */
    private const LINK_REGEX = 'regex:/^(https?:\/\/|\/)/i';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = app(BranchContext::class)->id();

        return [
            // Basic Site Information
            'site_title' => ['required', 'string', 'max:255'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string'],

            // Logo and Branding
            'header_logo_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'footer_logo_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'favicon_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'eiin' => ['nullable', 'string', 'max:20'],

            // Header call-to-action buttons. The URLs render as public hrefs, so they are
            // restricted to site-relative paths or http(s) — never javascript:/data:.
            'header_topbar_cta_label' => ['nullable', 'string', 'max:255'],
            'header_topbar_cta_url' => ['nullable', 'string', 'max:500', self::LINK_REGEX],
            'header_cta_label' => ['nullable', 'string', 'max:255'],
            'header_cta_url' => ['nullable', 'string', 'max:500', self::LINK_REGEX],

            // Contact Information
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string'],

            // Footer
            'footer_description' => ['nullable', 'string'],
            'footer_menus' => ['nullable', 'array', 'max:6'],
            'footer_menus.*.title' => ['required', 'string', 'max:255'],
            'footer_menus.*.menu_id' => ['required', 'integer', Rule::exists('menus', 'id')->where('branch_id', $branchId)],
            // Rich text: stored as HTML and sanitized in the controller before it is saved.
            'footer_copyright' => ['nullable', 'string', 'max:2000'],
            'footer_bottom_menu_id' => ['nullable', 'integer', Rule::exists('menus', 'id')->where('branch_id', $branchId)],

            // Social Media URLs
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'twitter_url' => ['nullable', 'url', 'max:500'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],

            // SEO and Analytics
            'google_analytics_code' => ['nullable', 'string'],
            'google_tag_manager_code' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string'],

            // Additional Settings
            'additional_settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_title.required' => 'The site title is required.',
            'header_logo_asset_id.exists' => 'The selected header logo is invalid.',
            'footer_logo_asset_id.exists' => 'The selected footer logo is invalid.',
            'favicon_asset_id.exists' => 'The selected favicon is invalid.',
            'header_topbar_cta_url.regex' => 'Enter a path starting with "/" or a full http(s) URL.',
            'header_cta_url.regex' => 'Enter a path starting with "/" or a full http(s) URL.',
            'footer_menus.*.title.required' => 'Give each footer column a title.',
            'footer_menus.*.menu_id.required' => 'Choose a menu for each footer column.',
            'footer_menus.*.menu_id.exists' => 'The selected menu is invalid.',
            'footer_bottom_menu_id.exists' => 'The selected footer bottom menu is invalid.',
            'contact_email.email' => 'Please enter a valid email address.',
            'facebook_url.url' => 'Please enter a valid URL.',
            'twitter_url.url' => 'Please enter a valid URL.',
            'linkedin_url.url' => 'Please enter a valid URL.',
            'youtube_url.url' => 'Please enter a valid URL.',
            'instagram_url.url' => 'Please enter a valid URL.',
        ];
    }
}
