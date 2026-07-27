import { useState } from 'react';
import { cn } from '@/lib/cn';
import { BranchSettingsPanel } from './BranchSettingsPanel';
import { SystemSettingsPanel } from './SystemSettingsPanel';
import { ProfileSettingsPanel } from './ProfileSettingsPanel';

const tabs = [
    { key: 'branch', label: 'Branch & Institution', render: () => <BranchSettingsPanel /> },
    { key: 'system', label: 'System & Environment', render: () => <SystemSettingsPanel /> },
    { key: 'profile', label: 'Profile & Security', render: () => <ProfileSettingsPanel /> },
];

export default function SettingsPage() {
    const [active, setActive] = useState('branch');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Settings</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Manage institution configuration, localization defaults, system diagnostics, and account security.
                </p>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setActive(t.key)}
                        className={cn(
                            'relative -mb-px rounded-t-lg px-3.5 py-2.5 text-[13.5px] font-medium transition-colors',
                            active === t.key
                                ? 'border-b-2 border-brand-600 text-brand-700 dark:text-brand-300'
                                : 'text-muted hover:text-fg',
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
