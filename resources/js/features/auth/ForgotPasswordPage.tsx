import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { GraduationCap, Loader2, CheckCircle2 } from 'lucide-react';
import * as authApi from './api';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';

export default function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [busy, setBusy] = useState(false);
    const [sent, setSent] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setBusy(true); setError(null);
        try {
            await authApi.forgotPassword(email);
            setSent(true);
        } catch (err) {
            setError(toApiError(err).message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="grid min-h-screen place-items-center bg-bg px-5">
            <div className="w-full max-w-[400px]">
                <div className="mb-8 flex items-center gap-2.5">
                    <div className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white"><GraduationCap size={21} /></div>
                    <span className="font-display text-lg font-extrabold text-fg">Decent<span className="text-brand-600 dark:text-brand-400">Edu</span></span>
                </div>

                <h2 className="font-display text-[26px] font-bold tracking-tight text-fg">Reset your password</h2>
                <p className="mt-1.5 text-[14px] text-muted">Enter your account email — we'll send a reset link if it's registered.</p>

                {sent ? (
                    <div className="mt-6 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-[13.5px] text-emerald-700 dark:border-emerald-500/25 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <CheckCircle2 size={16} className="mt-0.5 shrink-0" />
                        <span>If that email is registered, a reset link has been sent.</span>
                    </div>
                ) : (
                    <form onSubmit={onSubmit} className="mt-6 space-y-4">
                        {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
                        <div>
                            <label className="mb-1.5 block text-[13px] font-medium text-fg">Email</label>
                            <input
                                type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus
                                className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
                                placeholder="you@school.edu"
                            />
                        </div>
                        <button
                            type="submit" disabled={busy}
                            className={cn('flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-60')}
                        >
                            {busy && <Loader2 size={17} className="animate-spin" />} Send reset link
                        </button>
                    </form>
                )}

                <p className="mt-8 text-center text-[12.5px] text-faint">
                    <Link to="/login" className="font-medium text-brand-600 hover:underline dark:text-brand-400">Back to sign in</Link>
                </p>
            </div>
        </div>
    );
}
