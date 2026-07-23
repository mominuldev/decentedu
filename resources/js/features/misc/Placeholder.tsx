import { Construction } from 'lucide-react';
import { Card, Badge } from '@/components/ui';

export default function Placeholder({ title, phase }: { title: string; phase: string }) {
    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-[26px] font-bold tracking-tight text-fg">{title}</h1>
                <p className="mt-1 text-[14px] text-muted">This module is part of the DecentEdu rebuild roadmap.</p>
            </div>
            <Card className="grid place-items-center px-6 py-20 text-center">
                <div className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <Construction size={26} />
                </div>
                <h2 className="mt-4 text-[18px] font-semibold text-fg">{title} is coming soon</h2>
                <p className="mt-1 max-w-sm text-[13.5px] text-muted">
                    The dashboard and design system are live. This screen will be built out during{' '}
                    <span className="font-semibold text-fg">{phase}</span> following the architecture blueprint.
                </p>
                <Badge tone="brand" className="mt-4">{phase}</Badge>
            </Card>
        </div>
    );
}
