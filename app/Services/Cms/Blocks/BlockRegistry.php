<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks;

use App\Enums\Cms\BlockType;
use App\Services\Cms\Blocks\Types\AboutBlock;
use App\Services\Cms\Blocks\Types\CardListBlock;
use App\Services\Cms\Blocks\Types\CtaBlock;
use App\Services\Cms\Blocks\Types\DividerBlock;
use App\Services\Cms\Blocks\Types\FaqBlock;
use App\Services\Cms\Blocks\Types\GalleryBlock;
use App\Services\Cms\Blocks\Types\HeadingBlock;
use App\Services\Cms\Blocks\Types\HeroBlock;
use App\Services\Cms\Blocks\Types\HtmlBlock;
use App\Services\Cms\Blocks\Types\ImageBlock;
use App\Services\Cms\Blocks\Types\NoticeBoardBlock;
use App\Services\Cms\Blocks\Types\PageHeaderBlock;
use App\Services\Cms\Blocks\Types\PostsListBlock;
use App\Services\Cms\Blocks\Types\QuoteBlock;
use App\Services\Cms\Blocks\Types\RichTextBlock;
use App\Services\Cms\Blocks\Types\SectionBlock;
use App\Services\Cms\Blocks\Types\TeachersBlock;
use App\Services\Cms\Blocks\Types\VideoEmbedBlock;
use InvalidArgumentException;

/**
 * Registry of available block types. Bound as a singleton; projects can
 * register additional types in a service provider.
 */
class BlockRegistry
{
    /** @var array<string, BlockTypeContract> */
    private array $types = [];

    public function __construct()
    {
        foreach ($this->defaults() as $type) {
            $this->register($type);
        }
    }

    public function register(BlockTypeContract $type): void
    {
        $this->types[$type->type()->value] = $type;
    }

    public function get(BlockType|string $type): BlockTypeContract
    {
        $key = $type instanceof BlockType ? $type->value : $type;

        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown block type [{$key}].");
    }

    public function has(BlockType|string $type): bool
    {
        $key = $type instanceof BlockType ? $type->value : $type;

        return isset($this->types[$key]);
    }

    /**
     * @return array<string, BlockTypeContract>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Frontend-facing description of every registered type.
     *
     * @return array<int, array{type: string, label: string}>
     */
    public function options(): array
    {
        return array_values(array_map(
            fn (BlockTypeContract $type): array => [
                'type' => $type->type()->value,
                'label' => $type->type()->label(),
            ],
            $this->types,
        ));
    }

    /**
     * @return list<BlockTypeContract>
     */
    private function defaults(): array
    {
        return [
            new HeroBlock,
            new HeadingBlock,
            new CardListBlock,
            new NoticeBoardBlock,
            new QuoteBlock,
            new RichTextBlock,
            new ImageBlock,
            new GalleryBlock,
            new VideoEmbedBlock,
            new CtaBlock,
            new FaqBlock,
            new PostsListBlock,
            new HtmlBlock,
            new DividerBlock,
            new SectionBlock,
            new PageHeaderBlock,
            new TeachersBlock,
            new AboutBlock,
        ];
    }
}
