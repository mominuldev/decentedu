<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\MenuResource;
use App\Models\Cms\Asset;
use App\Models\Cms\Menu;
use App\Models\Cms\SiteSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Site-wide branding/identity for the rendered public site (favicon, logos, title,
 * contact and social links). Read-only counterpart of the admin
 * Cms\SiteSettingController: same row, but resolved for the branch pinned in
 * config('cms.public_branch_id') and stripped of everything a visitor has no business
 * seeing (row/author ids, timestamps, the free-form additional_settings bag).
 *
 * Never creates the settings row — an unconfigured branch yields nulls with a 200 so
 * the frontend can fall back to its baked-in defaults instead of erroring.
 */
class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = SiteSetting::query()
            ->with(['headerLogo.media', 'footerLogo.media', 'favicon.media', 'footerBottomMenu.items.linkable'])
            ->first();

        return ApiResponse::success($this->payload($settings), 'Site settings retrieved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?SiteSetting $s): array
    {
        return [
            'site_title' => $s?->site_title,
            'site_tagline' => $s?->site_tagline,
            'site_description' => $s?->site_description,
            'header_logo' => $this->asset($s?->headerLogo),
            'footer_logo' => $this->asset($s?->footerLogo),
            'favicon' => $this->asset($s?->favicon),
            'eiin' => $s?->eiin,
            'color_scheme' => $s?->color_scheme ?: config('cms.default_color_scheme'),
            'theme_colors' => $s?->resolvedThemeColors() ?? config('cms.color_schemes.'.config('cms.default_color_scheme').'.colors', []),
            'header_topbar_cta' => $this->cta($s?->header_topbar_cta_label, $s?->header_topbar_cta_url),
            'header_cta' => $this->cta($s?->header_cta_label, $s?->header_cta_url),
            'contact_email' => $s?->contact_email,
            'contact_phone' => $s?->contact_phone,
            'contact_address' => $s?->contact_address,
            'footer_description' => $s?->footer_description,
            'footer_menus' => $this->footerMenus($s),
            'footer_copyright' => $s?->footer_copyright,
            'footer_bottom_menu' => $this->bottomMenu($s),
            'facebook_url' => $s?->facebook_url,
            'twitter_url' => $s?->twitter_url,
            'linkedin_url' => $s?->linkedin_url,
            'youtube_url' => $s?->youtube_url,
            'instagram_url' => $s?->instagram_url,
            'meta_keywords' => $s?->meta_keywords,
        ];
    }

    /**
     * The footer link columns, each an authored title over the resolved items of a
     * chosen menu. Delivering the links inline saves the frontend one request per
     * column; columns keep their authored order, and any whose menu has since been
     * deleted or deactivated drop out rather than rendering an empty heading.
     *
     * @return list<array<string, mixed>>
     */
    private function footerMenus(?SiteSetting $s): array
    {
        $columns = collect($s?->footer_menus ?? [])->filter(fn ($c): bool => is_array($c));
        if ($columns->isEmpty()) {
            return [];
        }

        $menus = Menu::query()
            ->whereIn('id', $columns->pluck('menu_id')->filter()->all())
            ->where('is_active', true)
            ->with('items.linkable')
            ->get()
            ->keyBy('id');

        return $columns
            ->map(function (array $column) use ($menus): ?array {
                $menu = $menus->get($column['menu_id'] ?? null);
                if ($menu === null) {
                    return null;
                }

                $resolved = (new MenuResource($menu))->resolve();

                return [
                    'title' => $column['title'] ?? $menu->name,
                    'key' => $resolved['key'],
                    'items' => $resolved['items'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The small link row in the footer's bottom bar (privacy policy, terms and
     * conditions, sitemap), resolved from the chosen menu. Empty when no menu is chosen
     * or the chosen one has since been deactivated, so the frontend falls back to its
     * own defaults instead of losing the row.
     *
     * @return list<array<string, mixed>>
     */
    private function bottomMenu(?SiteSetting $s): array
    {
        $menu = $s?->footerBottomMenu;

        if ($menu === null || ! $menu->is_active) {
            return [];
        }

        return (new MenuResource($menu))->resolve()['items'];
    }

    /**
     * A button is only renderable with both halves, so a half-filled CTA collapses to
     * null and the frontend keeps its default rather than drawing a dead link.
     *
     * @return array<string, string>|null
     */
    private function cta(?string $label, ?string $url): ?array
    {
        if (blank($label) || blank($url)) {
            return null;
        }

        return ['label' => $label, 'url' => $url];
    }

    /**
     * Anonymous visitors can't hit the auth-gated media route, so private-category
     * assets are addressed through the public `site/media/{id}/file` server. URLs are
     * absolutised because the Next.js frontend renders them on a different origin.
     *
     * @return array<string, mixed>|null
     */
    private function asset(?Asset $asset): ?array
    {
        $file = $asset?->file();
        if ($asset === null || $file === null) {
            return null;
        }

        $url = $asset->isPrivate()
            ? "/api/v1/site/media/{$asset->id}/file"
            : $file->getUrl();

        return [
            'id' => $asset->id,
            'url' => url($url),
            'alt' => $asset->alt_text,
            'mime_type' => $file->mime_type,
        ];
    }
}
