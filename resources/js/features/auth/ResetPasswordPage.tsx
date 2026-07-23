import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { GraduationCap, Loader2 } from 'lucide-react';
import * as authApi from './api';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';

export default function ResetPasswordPage() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const token = params.get('token') ?? '';
    const [email, setEmail] = useState(params.get('email') ?? '');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErr, setFieldErr] = useState<Record<string, string[]>>({});

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setBusy(true); setError(null); setFieldErr({});
        try {
            await authApi.resetPassword({ token, email, password, password_confirmation: passwordConfirmation });
            navigate('/login', { replace: true });
        } catch (err) {
            const apiErr = toApiError(err);
            setError(apiErr.errors ? null : apiErr.message);
            setFieldErr(apiErr.errors ?? {});
        } finally {
            setBusy(false);
        }
    }

    const inputCls = (hasError: boolean) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );

    return (
        <div className="grid min-h-screen place-items-center bg-bg px-5">
            <div className="w-full max-w-[400px]">
                <div className="mb-8 flex items-center gap-2.5">
                    <div className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white"><GraduationCap size={21} /></div>
                    <span className="font-display text-lg font-extrabold text-fg">Decent<span className="text-brand-600 dark:text-brand-400">Edu</span></span>
                </div>

                <h2 className="font-display text-[26px] font-bold tracking-tight text-fg">Set a new password</h2>
                <p className="mt-1.5 text-[14px] text-muted">At least 8 characters, mixed case and a number.</p>

                <form onSubmit={onSubmit} className="mt-6 space-y-4">
                    {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Email</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required className={inputCls(!!fieldErr.email)} />
                        {fieldErr.email && <p className="mt-1.5 text-[12px] text-rose-500">{fieldErr.email[0]}</p>}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">New password</label>
                        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required className={inputCls(!!fieldErr.password)} />
                        {fieldErr.password && <p className="mt-1.5 text-[12px] text-rose-500">{fieldErr.password[0]}</p>}
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Confirm password</label>
                        <input type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required className={inputCls(false)} />
                    </div>
                    <button
                        type="submit" disabled={busy}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
                    >
                        {busy && <Loader2 size={17} className="animate-spin" />} Reset password
                    </button>
                </form>

                <p className="mt-8 text-center text-[12.5px] text-faint">
                    <Link to="/login" className="font-medium text-brand-600 hover:underline dark:text-brand-400">Back to sign in</Link>
                </p>
            </div>
        </div>
    );
}
