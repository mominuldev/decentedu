<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum RobotsDirective: string
{
    case IndexFollow = 'index,follow';
    case IndexNoFollow = 'index,nofollow';
    case NoIndexFollow = 'noindex,follow';
    case NoIndexNoFollow = 'noindex,nofollow';
}
