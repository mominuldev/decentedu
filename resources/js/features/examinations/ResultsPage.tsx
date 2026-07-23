import { useState } from 'react';
import { cn } from '@/lib/cn';
import { ResultProcessingPanel } from './ResultProcessingPanel';
import { ReportsPanel } from './ReportsPanel';

const tabs = [
    { key: 'process', label: 'Result Processing', render: () => <ResultProcessingPanel /> },
    { key: 'reports', label: 'Reports', render: () => <ReportsPanel /> },
];

export default function ResultsPage() {
    const [active, setActive] = useState('process');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Results</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Process exam results (general → final → merit) and generate marksheets, tabulation and merit/fail lists.
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
