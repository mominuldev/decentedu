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
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
            'mp4', 'webm', 'mp3',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Color scheme presets
    |--------------------------------------------------------------------------
    |
    | Curated palettes for the public Next.js site's 15 CSS custom properties
    | (see decentedu-fronend/src/app/globals.css). A branch picks one by key in
    | Site Settings → Branding and may layer per-token overrides on top; the
    | resolved map is what /api/v1/site/settings returns as `theme_colors`.
    | Add a preset here and it is immediately selectable — no frontend deploy.
    |
    */
    'default_color_scheme' => 'forest',

    'color_schemes' => [
        'forest' => [
            'label' => 'Forest & Crimson',
            'description' => 'Deep forest green and Bengali crimson on warm cream. The site\'s original look.',
            'colors' => [
                'primary' => '#04702f',
                'primary-dark' => '#0a3826',
                'primary-foreground' => '#ffffff',
                'secondary' => '#9e1b14',
                'secondary-dark' => '#8f1e17',
                'secondary-foreground' => '#ffffff',
                'accent' => '#0e4d34',
                'accent-foreground' => '#ffffff',
                'success' => '#0e4d34',
                'background' => '#faf5ea',
                'cream' => '#f4ecd8',
                'surface' => '#ffffff',
                'text' => '#171310',
                'muted' => '#5b6259',
                'border' => '#e7e0cd',
            ],
        ],
        'maroon' => [
            'label' => 'Royal Maroon & Gold',
            'description' => 'Deep maroon with a warm gold accent on soft cream.',
            'colors' => [
                'primary' => '#7a1128',
                'primary-dark' => '#4a0a19',
                'primary-foreground' => '#ffffff',
                'secondary' => '#b5811f',
                'secondary-dark' => '#9a6c16',
                'secondary-foreground' => '#1c1210',
                'accent' => '#5f0e20',
                'accent-foreground' => '#ffffff',
                'success' => '#16653c',
                'background' => '#faf3ea',
                'cream' => '#f3e8d8',
                'surface' => '#ffffff',
                'text' => '#1c1210',
                'muted' => '#66574f',
                'border' => '#e8dac6',
            ],
        ],
        'teal' => [
            'label' => 'Maritime Teal & Coral',
            'description' => 'Cool maritime teal with a coral accent on a light sea-glass background.',
            'colors' => [
                'primary' => '#0b6b6b',
                'primary-dark' => '#063f42',
                'primary-foreground' => '#ffffff',
                'secondary' => '#c1462f',
                'secondary-dark' => '#a63a26',
                'secondary-foreground' => '#ffffff',
                'accent' => '#0a5455',
                'accent-foreground' => '#ffffff',
                'success' => '#10694a',
                'background' => '#f1f6f4',
                'cream' => '#e3efec',
                'surface' => '#ffffff',
                'text' => '#12201e',
                'muted' => '#55645f',
                'border' => '#d3e2de',
            ],
        ],
        'indigo' => [
            'label' => 'Indigo & Amber',
            'description' => 'Modern indigo with a warm amber accent on a neutral off-white.',
            'colors' => [
                'primary' => '#26347a',
                'primary-dark' => '#161f4d',
                'primary-foreground' => '#ffffff',
                'secondary' => '#b4600d',
                'secondary-dark' => '#97500a',
                'secondary-foreground' => '#ffffff',
                'accent' => '#1f2c69',
                'accent-foreground' => '#ffffff',
                'success' => '#146c43',
                'background' => '#f5f5f1',
                'cream' => '#eceadf',
                'surface' => '#ffffff',
                'text' => '#14161f',
                'muted' => '#575a67',
                'border' => '#dedbcf',
            ],
        ],
        'navy' => [
            'label' => 'Slate Navy & Sky',
            'description' => 'Slate navy with a sky-blue accent on a cool off-white.',
            'colors' => [
                'primary' => '#1f3a5f',
                'primary-dark' => '#12243c',
                'primary-foreground' => '#ffffff',
                'secondary' => '#1d6a95',
                'secondary-dark' => '#175878',
                'secondary-foreground' => '#ffffff',
                'accent' => '#1a3252',
                'accent-foreground' => '#ffffff',
                'success' => '#14664a',
                'background' => '#f4f6f9',
                'cream' => '#e6ebf2',
                'surface' => '#ffffff',
                'text' => '#10151d',
                'muted' => '#566173',
                'border' => '#d6dde7',
            ],
        ],
    ],

];
