<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\SiteSettings\SiteSettingRequest;
use App\Models\Cms\SiteSetting;
use App\Support\ApiResponse;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = SiteSetting::forBranch();

        return ApiResponse::success($settings->toApiPayload(), 'Site settings retrieved.');
    }

    /**
     * The curated color-scheme presets (label, description, token map), for the admin's
     * Branding tab swatch picker. Source of truth is config('cms.color_schemes').
     */
    public function colorSchemes(): JsonResponse
    {
        return ApiResponse::success(config('cms.color_schemes', []), 'Color schemes retrieved.');
    }

    public function update(SiteSettingRequest $request): JsonResponse
    {
        $settings = SiteSetting::forBranch();
        $data = $request->validated();
        $userId = $request->user()->id;

        // Rich text from the WYSIWYG: sanitize on write, because this HTML is what the
        // public site renders with dangerouslySetInnerHTML.
        if (array_key_exists('footer_copyright', $data)) {
            $data['footer_copyright'] = filled($data['footer_copyright'])
                ? HtmlSanitizer::clean($data['footer_copyright'])
                : null;
        }

        $settings->fill([
            ...collect($data)->except(['header_logo', 'footer_logo', 'favicon'])->all(),
            'updated_by' => $userId,
        ]);

        // Handle logo relationships if asset IDs are provided
        if (isset($data['header_logo_asset_id'])) {
            $settings->header_logo_asset_id = $data['header_logo_asset_id'];
        }

        if (isset($data['footer_logo_asset_id'])) {
            $settings->footer_logo_asset_id = $data['footer_logo_asset_id'];
        }

        if (isset($data['favicon_asset_id'])) {
            $settings->favicon_asset_id = $data['favicon_asset_id'];
        }

        $settings->save();
        $settings->load(['headerLogo', 'footerLogo', 'favicon']);

        return ApiResponse::success($settings->toApiPayload(), 'Site settings updated successfully.');
    }

    public function reset(Request $request): JsonResponse
    {
        $settings = SiteSetting::forBranch();
        $userId = $request->user()->id;

        $settings->update([
            'site_title' => config('app.name', 'Safe Eduman'),
            'site_tagline' => null,
            'site_description' => null,
            'header_logo_asset_id' => null,
            'footer_logo_asset_id' => null,
            'favicon_asset_id' => null,
            'eiin' => null,
            'color_scheme' => null,
            'brand_colors' => null,
            'header_topbar_cta_label' => null,
            'header_topbar_cta_url' => null,
            'header_cta_label' => null,
            'header_cta_url' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'contact_address' => null,
            'footer_description' => null,
            'footer_menus' => null,
            'footer_copyright' => null,
            'footer_bottom_menu_id' => null,
            'facebook_url' => null,
            'twitter_url' => null,
            'linkedin_url' => null,
            'youtube_url' => null,
            'instagram_url' => null,
            'google_analytics_code' => null,
            'google_tag_manager_code' => null,
            'meta_keywords' => null,
            'additional_settings' => null,
            'updated_by' => $userId,
        ]);

        $settings->load(['headerLogo', 'footerLogo', 'favicon']);

        return ApiResponse::success($settings->toApiPayload(), 'Site settings reset to defaults.');
    }

    public function export(Request $request): StreamedResponse
    {
        $settings = SiteSetting::forBranch();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="site-settings-'.date('Y-m-d').'.csv"',
        ];

        return new StreamedResponse(function () use ($settings) {
            $output = fopen('php://output', 'w');

            // CSV headers
            fputcsv($output, [
                'Setting Key',
                'Setting Value',
                'Category',
                'Description',
            ]);

            // Settings data as key-value pairs
            $settingsData = [
                ['site_title', $settings->site_title, 'Basic', 'Site Title'],
                ['site_tagline', $settings->site_tagline ?? '', 'Basic', 'Site Tagline'],
                ['site_description', $settings->site_description ?? '', 'Basic', 'Site Description'],
                ['eiin', $settings->eiin ?? '', 'Branding', 'EIIN'],
                ['color_scheme', $settings->color_scheme ?? '', 'Branding', 'Color Scheme'],
                ['brand_colors', $settings->brand_colors ? json_encode($settings->brand_colors) : '', 'Branding', 'Color Overrides (JSON)'],
                ['header_topbar_cta_label', $settings->header_topbar_cta_label ?? '', 'Branding', 'Header Topbar CTA Label'],
                ['header_topbar_cta_url', $settings->header_topbar_cta_url ?? '', 'Branding', 'Header Topbar CTA URL'],
                ['header_cta_label', $settings->header_cta_label ?? '', 'Branding', 'Header CTA Label'],
                ['header_cta_url', $settings->header_cta_url ?? '', 'Branding', 'Header CTA URL'],
                ['contact_email', $settings->contact_email ?? '', 'Contact', 'Contact Email'],
                ['contact_phone', $settings->contact_phone ?? '', 'Contact', 'Contact Phone'],
                ['contact_address', $settings->contact_address ?? '', 'Contact', 'Contact Address'],
                // footer_menus is a repeater and footer_bottom_menu_id a branch-local row
                // id — neither belongs in a flat, portable key/value CSV, so export/import
                // leave them untouched.
                ['footer_description', $settings->footer_description ?? '', 'Footer', 'Footer Description'],
                ['footer_copyright', $settings->footer_copyright ?? '', 'Footer', 'Footer Copyright (HTML)'],
                ['facebook_url', $settings->facebook_url ?? '', 'Social Media', 'Facebook URL'],
                ['twitter_url', $settings->twitter_url ?? '', 'Social Media', 'Twitter URL'],
                ['linkedin_url', $settings->linkedin_url ?? '', 'Social Media', 'LinkedIn URL'],
                ['youtube_url', $settings->youtube_url ?? '', 'Social Media', 'YouTube URL'],
                ['instagram_url', $settings->instagram_url ?? '', 'Social Media', 'Instagram URL'],
                ['google_analytics_code', $settings->google_analytics_code ?? '', 'Analytics', 'Google Analytics Code'],
                ['google_tag_manager_code', $settings->google_tag_manager_code ?? '', 'Analytics', 'Google Tag Manager Code'],
                ['meta_keywords', $settings->meta_keywords ?? '', 'SEO', 'Meta Keywords'],
            ];

            foreach ($settingsData as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, 200, $headers);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $file = $request->file('file');
        $settings = SiteSetting::forBranch();
        $userId = $request->user()->id;

        // Parse CSV file
        $csvData = [];
        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            // Skip header row
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2 && ! empty($row[0])) {
                    $csvData[$row[0]] = $row[1];
                }
            }
            fclose($handle);
        }

        // Map CSV data to database fields
        $fieldMapping = [
            'site_title' => 'site_title',
            'site_tagline' => 'site_tagline',
            'site_description' => 'site_description',
            'eiin' => 'eiin',
            'color_scheme' => 'color_scheme',
            'brand_colors' => 'brand_colors',
            'header_topbar_cta_label' => 'header_topbar_cta_label',
            'header_topbar_cta_url' => 'header_topbar_cta_url',
            'header_cta_label' => 'header_cta_label',
            'header_cta_url' => 'header_cta_url',
            'contact_email' => 'contact_email',
            'contact_phone' => 'contact_phone',
            'contact_address' => 'contact_address',
            'footer_description' => 'footer_description',
            'footer_copyright' => 'footer_copyright',
            'facebook_url' => 'facebook_url',
            'twitter_url' => 'twitter_url',
            'linkedin_url' => 'linkedin_url',
            'youtube_url' => 'youtube_url',
            'instagram_url' => 'instagram_url',
            'google_analytics_code' => 'google_analytics_code',
            'google_tag_manager_code' => 'google_tag_manager_code',
            'meta_keywords' => 'meta_keywords',
        ];

        $updateData = [];
        foreach ($fieldMapping as $csvKey => $dbField) {
            if (isset($csvData[$csvKey])) {
                $value = trim($csvData[$csvKey]);

                // Sanitize URLs to prevent injection
                if (in_array($dbField, ['facebook_url', 'twitter_url', 'linkedin_url', 'youtube_url', 'instagram_url'])) {
                    if ($value === '') {
                        $updateData[$dbField] = null;
                    } elseif (filter_var($value, FILTER_VALIDATE_URL) && str_starts_with($value, 'https://')) {
                        $updateData[$dbField] = $value;
                    }

                    continue;
                }

                // CTA targets render as public hrefs; import bypasses the FormRequest, so
                // apply the same site-relative-or-http(s) restriction here.
                if (in_array($dbField, ['header_topbar_cta_url', 'header_cta_url'])) {
                    if ($value === '') {
                        $updateData[$dbField] = null;
                    } elseif (preg_match('/^(https?:\/\/|\/)/i', $value)) {
                        $updateData[$dbField] = $value;
                    }

                    continue;
                }

                // Sanitize email
                if ($dbField === 'contact_email') {
                    if ($value === '') {
                        $updateData[$dbField] = null;
                    } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $updateData[$dbField] = $value;
                    }

                    continue;
                }

                // Rich text: keep the markup, but purify it the same way the update
                // endpoint does rather than strip_tags-ing it down to plain text.
                if ($dbField === 'footer_copyright') {
                    $updateData[$dbField] = $value === '' ? null : HtmlSanitizer::clean($value);

                    continue;
                }

                // Color scheme: only a known preset key survives; anything else is dropped
                // rather than saved, since an unknown key would break resolvedThemeColors().
                if ($dbField === 'color_scheme') {
                    if ($value === '') {
                        $updateData[$dbField] = null;
                    } elseif (array_key_exists($value, config('cms.color_schemes', []))) {
                        $updateData[$dbField] = $value;
                    }

                    continue;
                }

                // Color overrides: a JSON object of token => hex. Non-token keys and
                // non-hex values are stripped rather than rejecting the whole row.
                if ($dbField === 'brand_colors') {
                    if ($value === '') {
                        $updateData[$dbField] = null;
                    } else {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $tokens = config('cms.color_schemes.'.config('cms.default_color_scheme', 'forest').'.colors', []);
                            $updateData[$dbField] = array_filter(
                                array_intersect_key($decoded, $tokens),
                                fn ($v): bool => is_string($v) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) === 1
                            );
                        }
                    }

                    continue;
                }

                // Sanitize tracking codes (only allow alphanumeric and basic script chars)
                if (in_array($dbField, ['google_analytics_code', 'google_tag_manager_code'])) {
                    if ($value === '') {
                        $updateData[$dbField] = null;
                    } else {
                        // Remove potentially dangerous characters, keep only safe tracking code patterns
                        $updateData[$dbField] = preg_replace('/[^a-zA-Z0-9\-_\.UA\-]/', '', $value);
                    }

                    continue;
                }

                // Convert empty strings to null for appropriate fields
                if ($value === '' && in_array($dbField, ['site_tagline', 'site_description', 'eiin', 'header_topbar_cta_label', 'header_cta_label', 'contact_phone', 'contact_address', 'footer_description', 'meta_keywords'])) {
                    $updateData[$dbField] = null;
                } else {
                    // Basic sanitization for text fields
                    $updateData[$dbField] = strip_tags($value);
                }
            }
        }

        // Ensure site_title is never empty
        if (empty($updateData['site_title'])) {
            $updateData['site_title'] = config('app.name', 'Safe Eduman');
        }

        $updateData['updated_by'] = $userId;

        $settings->update($updateData);
        $settings->load(['headerLogo', 'footerLogo', 'favicon']);

        return ApiResponse::success($settings->toApiPayload(), 'Site settings imported successfully.');
    }
}
