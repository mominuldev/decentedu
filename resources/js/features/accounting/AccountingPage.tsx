import { useState } from 'react';
import { cn } from '@/lib/cn';
import { LedgerAccountsPanel } from './LedgerAccountsPanel';
import { VouchersPanel } from './VouchersPanel';
import { AccountingReportsPanel } from './AccountingReportsPanel';

const tabs = [
    { key: 'ledgers', label: 'Chart of Accounts', render: () => <LedgerAccountsPanel /> },
    { key: 'vouchers', label: 'Vouchers', render: () => <VouchersPanel /> },
    { key: 'reports', label: 'Reports', render: () => <AccountingReportsPanel /> },
];

export default function AccountingPage() {
    const [active, setActive] = useState('ledgers');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Accounting</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Double-entry ledger — chart of accounts, vouchers, trial balance and income statement.
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
