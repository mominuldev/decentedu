import { type ReactNode, useRef } from 'react';
import { Printer, X } from 'lucide-react';
import { Button } from '@/components/ui';

/**
 * Print-friendly modal wrapper for a single document (TC/testimonial/certificate) or a bulk
 * grid (ID cards) — the app chrome is hidden and only `.print-area` is shown via @media print,
 * so the "Print" button just calls window.print() rather than generating a server PDF.
 */
export function PrintableDocument({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
    const areaRef = useRef<HTMLDivElement>(null);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 print:static print:p-0">
            <div className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm print:hidden" onClick={onClose} />
            <div className="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-border bg-surface shadow-[var(--shadow-pop)] print:max-h-none print:w-auto print:overflow-visible print:rounded-none print:border-0 print:shadow-none">
                <div className="flex items-center justify-between border-b border-border px-5 py-4 print:hidden">
                    <h3 className="text-[16px] font-semibold text-fg">{title}</h3>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={() => window.print()}><Printer size={16} /> Print</Button>
                        <button onClick={onClose} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-fg" aria-label="Close"><X size={18} /></button>
                    </div>
                </div>
                <div ref={areaRef} className="print-area bg-white p-8 text-slate-900">
                    {children}
                </div>
            </div>
        </div>
    );
}
