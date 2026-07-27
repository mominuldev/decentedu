<?php

declare(strict_types=1);

namespace App\Enums\Cms;

/**
 * The CMS content types a taxonomy can be assigned to. A taxonomy scoped to one or more of these
 * only surfaces (for term assignment) on the matching content editors, instead of being global.
 */
enum TaxonomyObjectType: string
{
    case Post = 'post';
    case Event = 'event';
    case Notice = 'notice';

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Posts',
            self::Event => 'Events',
            self::Notice => 'Notices',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
