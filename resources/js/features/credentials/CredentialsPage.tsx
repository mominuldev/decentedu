import { useState } from 'react';
import { cn } from '@/lib/cn';
import { TransferCertificatesPanel } from './TransferCertificatesPanel';
import { TestimonialsPanel } from './TestimonialsPanel';
import { CertificatesPanel } from './CertificatesPanel';
import { IdCardTemplatesPanel } from './IdCardTemplatesPanel';
import { IdCardsPanel } from './IdCardsPanel';

const tabs = [
    { key: 'tc', label: 'Transfer Certificates', render: () => <TransferCertificatesPanel /> },
    { key: 'testimonials', label: 'Testimonials', render: () => <TestimonialsPanel /> },
    { key: 'certificates', label: 'Certificates', render: () => <CertificatesPanel /> },
    { key: 'id-card-templates', label: 'ID Card Templates', render: () => <IdCardTemplatesPanel /> },
    { key: 'id-cards', label: 'Generate ID Cards', render: () => <IdCardsPanel /> },
];

export default function CredentialsPage() {
    const [active, setActive] = useState('tc');
    const current = tabs.find((t) => t.key === active) ?? tabs[0];

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">Credentials</h1>
                <p className="mt-1 text-[14px] text-muted">Transfer certificates, testimonials, certificates, and ID cards.</p>
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
