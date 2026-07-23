<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\WebsiteSetting;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $settings = WebsiteSetting::firstOrCreate(['branch_id' => $branchId]);

        return ApiResponse::success($settings, 'Website settings retrieved.');
    }

    public function update(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'site_title' => ['nullable', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'favicon_path' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'social_links' => ['nullable', 'array'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'boolean'],
        ]);

        $settings = WebsiteSetting::firstOrCreate(['branch_id' => $branchId]);
        $settings->update($data);

        return ApiResponse::success($settings, 'Website settings updated.');
    }
}
