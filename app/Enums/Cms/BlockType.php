<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum BlockType: string
{
    case Hero = 'hero';
    case Heading = 'heading';
    case PageHeader = 'page_header';
    case RichText = 'rich_text';
    case Image = 'image';
    case Gallery = 'gallery';
    case VideoEmbed = 'video_embed';
    case Cta = 'cta';
    case Faq = 'faq';
    case PostsList = 'posts_list';
    case Html = 'html';
    case Divider = 'divider';
    case Section = 'section';
    case CardList = 'card_list';
    case NoticeBoard = 'notice_board';
    case Quote = 'quote';
    case Teachers = 'teachers';
    case About = 'about';
    case MilestonesTimeline = 'milestones_timeline';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::Heading => 'Heading',
            self::PageHeader => 'Page Header',
            self::RichText => 'Rich Text',
            self::Image => 'Image',
            self::Gallery => 'Gallery',
            self::VideoEmbed => 'Video Embed',
            self::Cta => 'Call to Action',
            self::Faq => 'FAQ',
            self::PostsList => 'Posts List',
            self::Html => 'Custom HTML',
            self::Divider => 'Divider',
            self::Section => 'Section',
            self::CardList => 'Card List',
            self::NoticeBoard => 'Notice Board',
            self::Quote => 'Quote',
            self::Teachers => 'Teachers',
            self::About => 'About',
            self::MilestonesTimeline => 'Milestones Timeline',
        };
    }
}
