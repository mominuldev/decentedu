import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

/* ---- Card ---------------------------------------------------------------- */
export function Card({ className, children }: { className?: string; children: ReactNode }) {
    return (
        <div
            className={cn(
                'rounded-2xl border border-border bg-surface shadow-[var(--shadow-card)]',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function CardHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 px-5 pt-5">
            <div>
                <h3 className="text-[15px] font-semibold text-fg">{title}</h3>
                {subtitle && <p className="mt-0.5 text-[13px] text-muted">{subtitle}</p>}
            </div>
            {action}
        </div>
    );
}

/* ---- Badge --------------------------------------------------------------- */
type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'neutral' | 'sky';
const toneMap: Record<Tone, string> = {
    brand: 'text-brand-700 bg-brand-50 ring-brand-200 dark:text-brand-300 dark:bg-brand-500/10 dark:ring-brand-500/25',
    success: 'text-emerald-700 bg-emerald-50 ring-emerald-200 dark:text-emerald-300 dark:bg-emerald-500/10 dark:ring-emerald-500/25',
    warning: 'text-amber-700 bg-amber-50 ring-amber-200 dark:text-amber-300 dark:bg-amber-500/10 dark:ring-amber-500/25',
    danger: 'text-rose-700 bg-rose-50 ring-rose-200 dark:text-rose-300 dark:bg-rose-500/10 dark:ring-rose-500/25',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200 dark:text-sky-300 dark:bg-sky-500/10 dark:ring-sky-500/25',
    neutral: 'text-muted bg-surface-2 ring-border',
};

export function Badge({ tone = 'neutral', size = 'md', children, className }: { tone?: Tone; size?: 'sm' | 'md'; children: ReactNode; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full font-medium ring-1 ring-inset',
                size === 'sm' ? 'px-2 py-0 text-[11px]' : 'px-2.5 py-0.5 text-xs',
                toneMap[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}

/* ---- Button -------------------------------------------------------------- */
interface BtnProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'ghost' | 'outline';
    size?: 'sm' | 'md';
    children: ReactNode;
}
export function Button({ variant = 'primary', size = 'md', className, children, ...rest }: BtnProps) {
    const base = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-colors disabled:opacity-50';
    const sizes = {
        sm: 'px-2.5 py-1.5 text-xs',
        md: 'px-3.5 py-2 text-sm',
    } as const;
    const variants = {
        primary: 'bg-brand-600 text-white hover:bg-brand-700 shadow-[var(--shadow-soft)]',
        outline: 'border border-border-strong text-fg hover:bg-surface-2',
        ghost: 'text-muted hover:bg-surface-2 hover:text-fg',
    } as const;
    return (
        <button className={cn(base, sizes[size], variants[variant], className)} {...rest}>
            {children}
        </button>
    );
}

/* ---- IconButton ---------------------------------------------------------- */
export function IconButton({ className, children, ...rest }: BtnProps) {
    return (
        <button
            className={cn(
                'grid h-9 w-9 place-items-center rounded-xl text-muted transition-colors hover:bg-surface-2 hover:text-fg',
                className,
            )}
            {...rest}
        >
            {children}
        </button>
    );
}
