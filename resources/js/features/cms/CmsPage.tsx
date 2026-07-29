import { useSearchParams } from 'react-router-dom';
import { FileText, Newspaper, Tags, Image, Menu as MenuIcon, CornerUpRight, Megaphone, CalendarDays, Images, Settings } from 'lucide-react';
import { cn } from '@/lib/cn';
import { PagesPanel } from './PagesPanel';
import { PostsPanel } from './PostsPanel';
import { GalleryPanel } from './GalleryPanel';
import { TaxonomiesPanel } from './TaxonomiesPanel';
import { MediaPanel } from './MediaPanel';
import { MenusPanel } from './MenusPanel';
import { NoticesPanel } from './NoticesPanel';
import { EventsPanel } from './EventsPanel';
import { RedirectsPanel } from './RedirectsPanel';
import { SettingsPanel } from './SettingsPanel';

const sections = [
    { key: 'pages', label: 'Pages', icon: FileText, render: () => <PagesPanel /> },
    { key: 'posts', label: 'Posts', icon: Newspaper, render: () => <PostsPanel /> },
    { key: 'gallery', label: 'Gallery', icon: Images, render: () => <GalleryPanel /> },
    { key: 'notices', label: 'Notices', icon: Megaphone, render: () => <NoticesPanel /> },
    { key: 'events', label: 'Events', icon: CalendarDays, render: () => <EventsPanel /> },
    { key: 'taxonomies', label: 'Taxonomies', icon: Tags, render: () => <TaxonomiesPanel /> },
    { key: 'media', label: 'Media', icon: Image, render: () => <MediaPanel /> },
    { key: 'menus', label: 'Menus', icon: MenuIcon, render: () => <MenusPanel /> },
    { key: 'redirects', label: 'Redirects', icon: CornerUpRight, render: () => <RedirectsPanel /> },
    { key: 'settings', label: 'Settings', icon: Settings, render: () => <SettingsPanel /> },
] as const;

type SectionKey = (typeof sections)[number]['key'];

export default function CmsPage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const active = (sections.find((s) => s.key === searchParams.get('tab'))?.key ?? 'pages') as SectionKey;
    const setActive = (key: SectionKey) => setSearchParams(key === 'pages' ? {} : { tab: key });
    const current = sections.find((s) => s.key === active) ?? sections[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">CMS</h1>
                <p className="mt-1 text-[14px] text-muted">Pages, posts, taxonomies, media, menus, and redirects for the public site.</p>
            </div>

            <div className="flex flex-wrap gap-1.5">
                {sections.map((s) => {
                    const Icon = s.icon;
                    return (
                        <button key={s.key} onClick={() => setActive(s.key)}
                            className={cn(
                                'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-[13.5px] font-medium transition-colors',
                                active === s.key ? 'bg-brand-600 text-white shadow-[var(--shadow-soft)]' : 'text-muted hover:bg-surface-2 hover:text-fg',
                            )}>
                            <Icon size={16} /> {s.label}
                        </button>
                    );
                })}
            </div>

            {current.render()}
        </div>
    );
}
