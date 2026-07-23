import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { cn } from '@/lib/cn';
import { SetupResource, type FieldDef } from './SetupResource';
import { listSetup } from './api';
import { FeeConfigPanel } from './FeeConfigPanel';
import { FeeTimeConfigPanel } from './FeeTimeConfigPanel';
import { FeeWaiverAssignPanel } from './FeeWaiverAssignPanel';
import { DuesCollectionPanel } from './DuesCollectionPanel';
import { ReceiptsPanel } from './ReceiptsPanel';
import { FeeReportsPanel } from './FeeReportsPanel';

const waiverTypeField: FieldDef = {
    name: 'type',
    label: 'Type',
    type: 'select',
    required: true,
    options: [
        { value: 'percentage', label: 'Percentage' },
        { value: 'fixed', label: 'Fixed amount' },
    ],
};
const waiverValueField: FieldDef = { name: 'value', label: 'Value', type: 'number', required: true };

function SubHeadsTab() {
    const { data: heads = [] } = useQuery({ queryKey: ['fees-setup', 'heads'], queryFn: () => listSetup('heads') });
    const feeHeadField: FieldDef = {
        name: 'fee_head_id', label: 'Fee head', type: 'select', required: true,
        options: heads.map((h) => ({ value: String(h.id), label: h.name })),
    };

    return <SetupResource resource="sub-heads" singular="Sub-head" extraFields={[feeHeadField]} />;
}

interface Tab { key: string; label: string; render: () => React.ReactNode }

const setupTabs: Tab[] = [
    { key: 'heads', label: 'Fee Heads', render: () => <SetupResource resource="heads" singular="Fee head" /> },
    { key: 'sub-heads', label: 'Sub-heads', render: () => <SubHeadsTab /> },
    { key: 'waivers', label: 'Waivers', render: () => <SetupResource resource="waivers" singular="Waiver" extraFields={[waiverTypeField, waiverValueField]} /> },
];

const configTabs: Tab[] = [
    { key: 'fee-structure', label: 'Fee Structure', render: () => <FeeConfigPanel /> },
    { key: 'time-config', label: 'Due Date & Fine', render: () => <FeeTimeConfigPanel /> },
    { key: 'waiver-assign', label: 'Waiver Assignment', render: () => <FeeWaiverAssignPanel /> },
];

const collectionTabs: Tab[] = [
    { key: 'dues', label: 'Dues & Collection', render: () => <DuesCollectionPanel /> },
    { key: 'receipts', label: 'Receipts', render: () => <ReceiptsPanel /> },
];

const topTabs = [
    { key: 'setup', label: 'Setup', tabs: setupTabs },
    { key: 'config', label: 'Configuration', tabs: configTabs },
    { key: 'collection', label: 'Collection', tabs: collectionTabs },
    { key: 'reports', label: 'Reports', tabs: [{ key: 'reports', label: 'Reports', render: () => <FeeReportsPanel /> }] },
];

function PillTabs({ tabs, active, onChange }: { tabs: Tab[]; active: string; onChange: (key: string) => void }) {
    if (tabs.length <= 1) return null;

    return (
        <div className="flex flex-wrap gap-1.5">
            {tabs.map((t) => (
                <button
                    key={t.key}
                    onClick={() => onChange(t.key)}
                    className={cn(
                        'rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors',
                        active === t.key ? 'bg-brand-600 text-white' : 'bg-surface-2 text-muted hover:text-fg',
                    )}
                >
                    {t.label}
                </button>
            ))}
        </div>
    );
}

export default function FeesPage() {
    const [activeTop, setActiveTop] = useState('setup');
    const [activeSub, setActiveSub] = useState<Record<string, string>>({});

    const top = topTabs.find((t) => t.key === activeTop) ?? topTabs[0];
    const subKey = activeSub[top.key] ?? top.tabs[0].key;
    const currentSub = top.tabs.find((t) => t.key === subKey) ?? top.tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Fees</h1>
                <p className="mt-1 text-[14px] text-muted">
                    Fee structure, waivers, dues, and collection receipts.
                </p>
            </div>

            <div className="flex flex-wrap gap-1.5 border-b border-border">
                {topTabs.map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setActiveTop(t.key)}
                        className={cn(
                            'relative -mb-px rounded-t-lg px-3.5 py-2.5 text-[13.5px] font-medium transition-colors',
                            activeTop === t.key
                                ? 'border-b-2 border-brand-600 text-brand-700 dark:text-brand-300'
                                : 'text-muted hover:text-fg',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <PillTabs tabs={top.tabs} active={subKey} onChange={(key) => setActiveSub((s) => ({ ...s, [top.key]: key }))} />

            {currentSub.render()}
        </div>
    );
}
