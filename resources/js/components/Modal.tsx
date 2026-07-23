import { useEffect, type ReactNode } from 'react';
import { X, AlertTriangle } from 'lucide-react';
import { Button } from './ui';

export function Modal({
    open, onClose, title, children, footer, width = 'max-w-lg',
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    footer?: ReactNode;
    width?: string;
}) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" onClick={onClose} />
            <div className={`relative w-full ${width} rounded-2xl border border-border bg-surface shadow-[var(--shadow-pop)]`}>
                <div className="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 className="text-[16px] font-semibold text-fg">{title}</h3>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-fg" aria-label="Close">
                        <X size={18} />
                    </button>
                </div>
                <div className="px-5 py-5">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-border px-5 py-4">{footer}</div>}
            </div>
        </div>
    );
}

export function ConfirmDialog({
    open, onClose, onConfirm, title, message, busy,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    busy?: boolean;
}) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={busy}>Cancel</Button>
                    <Button className="bg-rose-600 hover:bg-rose-700" onClick={onConfirm} disabled={busy}>
                        {busy ? 'Deleting…' : 'Delete'}
                    </Button>
                </>
            }
        >
            <div className="flex items-start gap-3">
                <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <AlertTriangle size={20} />
                </div>
                <p className="pt-1.5 text-[14px] text-muted">{message}</p>
            </div>
        </Modal>
    );
}
