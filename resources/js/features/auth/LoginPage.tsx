import { useState, type FormEvent, type ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';
import { GraduationCap, Eye, EyeOff, Loader2, ShieldCheck, Building2, BarChart3, AlertCircle } from 'lucide-react';
import { useAuth } from './AuthProvider';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';

const pillars = [
    { icon: Building2, title: 'Every branch, one place', sub: 'Switch schools without switching accounts' },
    { icon: BarChart3, title: 'Exams, fees & results', sub: 'The full academic cycle, end to end' },
    { icon: ShieldCheck, title: 'Role-based & secure', sub: 'Branch-scoped access, encrypted sessions' },
];

export default function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('demo@decentedu.test');
    const [password, setPassword] = useState('password');
    const [remember, setRemember] = useState(true);
    const [show, setShow] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErr, setFieldErr] = useState<Record<string, string[]>>({});

    async function onSubmit(e: FormEvent) {
        e.preventDefault();
        setBusy(true); setError(null); setFieldErr({});
        try {
            await login(email, password, remember);
            navigate('/', { replace: true });
        } catch (err) {
            const apiErr = toApiError(err);
            setError(apiErr.message);
            setFieldErr(apiErr.errors ?? {});
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="grid min-h-screen lg:grid-cols-2">
            {/* Brand panel */}
            <div className="relative hidden overflow-hidden bg-brand-700 lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div
                    className="pointer-events-none absolute inset-0 opacity-90"
                    style={{ background: 'radial-gradient(1200px 500px at 15% -10%, #6d5ff0 0%, transparent 55%), radial-gradient(900px 600px at 110% 120%, #3a2f9e 0%, transparent 50%)' }}
                />
                <div className="relative flex items-center gap-3 text-white">
                    <div className="grid h-11 w-11 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                        <GraduationCap size={24} strokeWidth={2.3} />
                    </div>
                    <div>
                        <div className="font-display text-xl font-extrabold tracking-tight">DecentEdu</div>
                        <div className="text-[11px] font-medium uppercase tracking-[0.16em] text-white/70">School Suite</div>
                    </div>
                </div>

                <div className="relative text-white">
                    <h1 className="max-w-md font-display text-[34px] font-bold leading-[1.15] tracking-tight text-balance">
                        Manage every branch, routine and result — in one place.
                    </h1>
                    <div className="mt-9 space-y-4">
                        {pillars.map((p) => (
                            <div key={p.title} className="flex items-start gap-3.5">
                                <div className="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/12 ring-1 ring-white/20">
                                    <p.icon size={17} />
                                </div>
                                <div>
                                    <div className="text-[14px] font-semibold">{p.title}</div>
                                    <div className="text-[12.5px] text-white/65">{p.sub}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="relative text-[12px] text-white/55">Protected access · 2026 © DecentEdu</div>
            </div>

            {/* Form panel */}
            <div className="flex items-center justify-center bg-bg px-5 py-10">
                <div className="w-full max-w-[400px]">
                    <div className="mb-8 flex items-center gap-2.5 lg:hidden">
                        <div className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white">
                            <GraduationCap size={21} />
                        </div>
                        <span className="font-display text-lg font-extrabold text-fg">
                            Decent<span className="text-brand-600 dark:text-brand-400">Edu</span>
                        </span>
                    </div>

                    <h2 className="font-display text-[26px] font-bold tracking-tight text-fg">Welcome back</h2>
                    <p className="mt-1.5 text-[14px] text-muted">Sign in to your school registry.</p>

                    {error && (
                        <div className="mt-5 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">
                            <AlertCircle size={16} className="mt-0.5 shrink-0" />
                            <span>{error}</span>
                        </div>
                    )}

                    <form onSubmit={onSubmit} className="mt-6 space-y-4">
                        <Field label="Email" error={fieldErr.email?.[0]}>
                            <input
                                type="email" value={email} onChange={(e) => setEmail(e.target.value)}
                                autoComplete="email" required autoFocus
                                className={inputCls(!!fieldErr.email)}
                                placeholder="you@school.edu"
                            />
                        </Field>

                        <Field label="Password" error={fieldErr.password?.[0]}>
                            <div className="relative">
                                <input
                                    type={show ? 'text' : 'password'} value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    autoComplete="current-password" required
                                    className={cn(inputCls(!!fieldErr.password), 'pr-11')}
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button" onClick={() => setShow((s) => !s)}
                                    className="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-faint hover:text-fg"
                                    aria-label={show ? 'Hide password' : 'Show password'}
                                >
                                    {show ? <EyeOff size={17} /> : <Eye size={17} />}
                                </button>
                            </div>
                        </Field>

                        <div className="flex items-center justify-between pt-0.5">
                            <label className="flex cursor-pointer select-none items-center gap-2 text-[13px] text-muted">
                                <input
                                    type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)}
                                    className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500"
                                />
                                Keep me signed in
                            </label>
                            <a href="/forgot-password" className="text-[13px] font-medium text-brand-600 hover:underline dark:text-brand-400">
                                Forgot password?
                            </a>
                        </div>

                        <button
                            type="submit" disabled={busy}
                            className="mt-2 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-[14px] font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-60"
                        >
                            {busy && <Loader2 size={17} className="animate-spin" />}
                            {busy ? 'Signing in…' : 'Sign in to Registry'}
                        </button>
                    </form>

                    <p className="mt-8 text-center text-[12px] text-faint">
                        Demo credentials are pre-filled · <span className="font-mono">demo@decentedu.test</span>
                    </p>
                </div>
            </div>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-medium text-fg">{label}</label>
            {children}
            {error && <p className="mt-1.5 text-[12px] text-rose-500">{error}</p>}
        </div>
    );
}

function inputCls(hasError: boolean) {
    return cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none transition-colors placeholder:text-faint',
        'focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );
}
