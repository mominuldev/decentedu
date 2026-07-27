<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Public site branch
    |--------------------------------------------------------------------------
    |
    | The rendered public marketing site (Next.js frontend) is served by the
    | unauthenticated /api/v1/site/* endpoints. Those visitors have no session,
    | so the branch whose published content they see is pinned here rather than
    | resolved from auth. Set CMS_PUBLIC_BRANCH_ID to the branch that owns the
    | public website's content.
    |
    */
    'public_branch_id' => env('CMS_PUBLIC_BRANCH_ID'),

    /*
    |--------------------------------------------------------------------------
    | Page templates
    |--------------------------------------------------------------------------
    |
    | Slug => label. Templates are a hint for the consuming frontend (which
    | decides how to render each one); the admin offers this list when editing
    | a page. Add project-specific templates here.
    |
    */
    'templates' => [
        'default' => 'Default',
        'home' => 'Home',
        'landing' => 'Landing',
        'full-width' => 'Full Width',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'max_kb' => 20480,
        'accepted_mimes' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
            'mp4', 'webm', 'mp3',
        ],
    ],

];
