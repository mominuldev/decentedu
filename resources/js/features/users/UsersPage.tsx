import { useState } from 'react';
import { cn } from '@/lib/cn';
import { useAuth } from '@/features/auth/AuthProvider';
import { UsersPanel } from './UsersPanel';
import { RolesPanel } from './RolesPanel';
import { SessionsPanel } from './SessionsPanel';

const tabs = [
    { key: 'users', label: 'Users', render: () => <UsersPanel /> },
    { key: 'roles', label: 'Roles & Permissions', render: () => <RolesPanel /> },
    { key: 'account', label: 'My Account', render: () => <SessionsPanel /> },
];

export default function UsersPage() {
    const { session } = useAuth();
    const [active, setActive] = useState(session?.user.must_reset_password ? 'account' : 'users');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Users & Roles</h1>
                <p className="mt-1 text-[14px] text-muted">
                    {session?.user.must_reset_password
                        ? 'You must set a new password before continuing.'
                        : 'Manage accounts, branch access, and module permissions.'}
                </p>
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
