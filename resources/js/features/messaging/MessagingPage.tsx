import { useState } from 'react';
import { cn } from '@/lib/cn';
import { SendPanel } from './SendPanel';
import { TemplatesPanel } from './TemplatesPanel';
import { ContactsPanel } from './ContactsPanel';
import { BatchesPanel } from './BatchesPanel';
import { BalancePanel } from './BalancePanel';

const tabs = [
    { key: 'send', label: 'Send', render: () => <SendPanel /> },
    { key: 'templates', label: 'Templates', render: () => <TemplatesPanel /> },
    { key: 'contacts', label: 'Contacts', render: () => <ContactsPanel /> },
    { key: 'reports', label: 'Delivery Reports', render: () => <BatchesPanel /> },
    { key: 'balance', label: 'Balance', render: () => <BalancePanel /> },
];

export default function MessagingPage() {
    const [active, setActive] = useState('send');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">SMS & Notices</h1>
                <p className="mt-1 text-[14px] text-muted">Templates, phone book, sending, delivery reports, and balance.</p>
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
