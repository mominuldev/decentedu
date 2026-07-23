import { useState } from 'react';
import { cn } from '@/lib/cn';
import { PostsPanel } from './PostsPanel';
import { MenusPanel } from './MenusPanel';
import { SettingsPanel } from './SettingsPanel';

const tabs = [
    { key: 'posts', label: 'Posts', render: () => <PostsPanel /> },
    { key: 'menus', label: 'Menus', render: () => <MenusPanel /> },
    { key: 'settings', label: 'Settings', render: () => <SettingsPanel /> },
];

export default function CmsPage() {
    const [active, setActive] = useState('posts');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Website</h1>
                <p className="mt-1 text-[14px] text-muted">Posts, menus, and public-site settings.</p>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setActive(t.key)}
                        className={cn(
                            'relative -mb-px rounded-t-lg px-3.5 py-2.5 text-[13.5px] font-medium transition-colors',
                            active === t.key ? 'border-b-2 border-brand-600 text-brand-700 dark:text-brand-300' : 'text-muted hover:text-fg',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {current.render()}
        </div>
    );
}
