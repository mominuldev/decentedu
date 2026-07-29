<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\SiteSettings;

use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteSettingRequest extends FormRequest
{
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

            // Contact Information
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string'],

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
            'contact_email.email' => 'Please enter a valid email address.',
            'facebook_url.url' => 'Please enter a valid URL.',
            'twitter_url.url' => 'Please enter a valid URL.',
            'linkedin_url.url' => 'Please enter a valid URL.',
            'youtube_url.url' => 'Please enter a valid URL.',
            'instagram_url.url' => 'Please enter a valid URL.',
        ];
    }
}