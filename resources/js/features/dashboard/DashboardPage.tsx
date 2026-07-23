import {
    ResponsiveContainer, AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip,
    BarChart, Bar, Legend,
} from 'recharts';
import {
    Users, CalendarCheck, Wallet, TrendingDown, ArrowUpRight, ArrowDownRight,
    UserPlus, ClipboardList, MessageSquare, ReceiptText, Plus,
} from 'lucide-react';
import { Card, CardHeader, Badge, Button } from '@/components/ui';
import { useAuth } from '@/features/auth/AuthProvider';
import { cn, num, money } from '@/lib/cn';
import {
    stats, attendanceTrend, collectionByMonth, enrollmentByClass, notices,
    upcomingExams, recentAdmissions,
} from '@/data/dashboard';

const statIcon: Record<string, typeof Users> = {
    students: Users, present: CalendarCheck, collection: Wallet, dues: TrendingDown,
};

function Sparkline({ points, tone }: { points: number[]; tone: string }) {
    const w = 96, h = 32, min = Math.min(...points), max = Math.max(...points);
    const span = max - min || 1;
    const step = w / (points.length - 1);
    const d = points
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${(i * step).toFixed(1)},${(h - ((p - min) / span) * h).toFixed(1)}`)
        .join(' ');
    return (
        <svg width={w} height={h} className="overflow-visible" aria-hidden>
            <path d={d} fill="none" stroke={tone} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
            <circle cx={w} cy={h - ((points[points.length - 1] - min) / span) * h} r={2.6} fill={tone} />
        </svg>
    );
}

function StatCard({ s }: { s: (typeof stats)[number] }) {
    const Icon = statIcon[s.key] ?? Users;
    const positive = (s.delta ?? 0) >= 0;
    const warn = s.tone === 'warning';
    const sparkTone = warn ? '#f59e0b' : positive ? '#10b981' : '#f43f5e';
    return (
        <Card className="p-5">
            <div className="flex items-start justify-between">
                <div className={cn(
                    'grid h-11 w-11 place-items-center rounded-xl',
                    warn ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'
                         : 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
                )}>
                    <Icon size={21} strokeWidth={2.1} />
                </div>
                <Sparkline points={s.spark} tone={sparkTone} />
            </div>
            <div className="mt-4 text-[13px] font-medium text-muted">{s.label}</div>
            <div className="mt-1 flex items-end justify-between gap-2">
                <div className="tnum font-display text-[28px] font-bold leading-none text-fg">
                    {s.money ? money(s.value) : num(s.value)}
                </div>
                {s.delta !== undefined && (
                    <span className={cn(
                        'mb-0.5 inline-flex items-center gap-0.5 text-[12.5px] font-semibold',
                        positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500',
                    )}>
                        {positive ? <ArrowUpRight size={14} /> : <ArrowDownRight size={14} />}
                        {Math.abs(s.delta)}%
                    </span>
                )}
            </div>
            {s.sub && <div className="mt-1 text-[12px] text-faint">{s.sub}</div>}
        </Card>
    );
}

const chartTooltip = {
    contentStyle: {
        background: 'var(--surface)', border: '1px solid var(--border)',
        borderRadius: '12px', boxShadow: 'var(--shadow-pop)', fontSize: 12,
    },
    labelStyle: { color: 'var(--muted)', fontWeight: 600, marginBottom: 2 },
    itemStyle: { color: 'var(--fg)' },
} as const;

const quickActions = [
    { label: 'Register student', icon: UserPlus },
    { label: 'Collect fee', icon: ReceiptText },
    { label: 'Input marks', icon: ClipboardList },
    { label: 'Send SMS', icon: MessageSquare },
];

export default function DashboardPage() {
    const { session } = useAuth();
    const firstName = session?.user.name.split(' ')[0] ?? 'there';
    const branchName = session?.active_branch?.name ?? 'your branch';
    return (
        <div className="space-y-6">
            {/* header */}
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-[26px] font-bold tracking-tight text-fg">Good morning, {firstName} 👋</h1>
                    <p className="mt-1 text-[14px] text-muted">
                        Here’s what’s happening at <span className="font-semibold text-fg">{branchName}</span> today,
                        Thursday 23 July.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline">Export</Button>
                    <Button><Plus size={16} /> Quick action</Button>
                </div>
            </div>

            {/* KPI row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((s) => <StatCard key={s.key} s={s} />)}
            </div>

            {/* charts row */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader
                        title="Attendance trend"
                        subtitle="Daily present vs absent — last 12 school days"
                        action={<Badge tone="success">91.4% avg</Badge>}
                    />
                    <div className="px-2 pb-3 pt-4">
                        <ResponsiveContainer width="100%" height={260}>
                            <AreaChart data={attendanceTrend} margin={{ top: 4, right: 12, left: -14, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="gPresent" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#5343e0" stopOpacity={0.32} />
                                        <stop offset="100%" stopColor="#5343e0" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                                <XAxis dataKey="d" tick={{ fontSize: 11, fill: 'var(--faint)' }} tickLine={false} axisLine={false} />
                                <YAxis domain={[80, 100]} tick={{ fontSize: 11, fill: 'var(--faint)' }} tickLine={false} axisLine={false} unit="%" />
                                <Tooltip {...chartTooltip} />
                                <Area type="monotone" dataKey="present" name="Present" stroke="#5343e0" strokeWidth={2.5} fill="url(#gPresent)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                {/* quick actions + notices */}
                <div className="space-y-4">
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg">Quick actions</h3>
                        <div className="mt-3 grid grid-cols-2 gap-2.5">
                            {quickActions.map((a) => (
                                <button
                                    key={a.label}
                                    className="flex flex-col items-start gap-2 rounded-xl border border-border bg-surface-2/50 p-3 text-left transition-colors hover:border-brand-300 hover:bg-brand-50/60 dark:hover:bg-brand-500/10"
                                >
                                    <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                        <a.icon size={16} />
                                    </span>
                                    <span className="text-[12.5px] font-semibold text-fg">{a.label}</span>
                                </button>
                            ))}
                        </div>
                    </Card>
                    <Card className="p-5">
                        <div className="flex items-center justify-between">
                            <h3 className="text-[15px] font-semibold text-fg">SMS balance</h3>
                            <Badge tone="brand">Active</Badge>
                        </div>
                        <div className="mt-2 tnum font-display text-[26px] font-bold text-fg">14,820 <span className="text-[13px] font-medium text-faint">credits</span></div>
                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-surface-2">
                            <div className="h-full w-[68%] rounded-full bg-brand-600" />
                        </div>
                        <p className="mt-2 text-[12px] text-faint">≈ 9 days at current send rate</p>
                    </Card>
                </div>
            </div>

            {/* second charts row */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader title="Enrollment by class" subtitle="Boys vs girls across classes Six–Ten" />
                    <div className="px-2 pb-3 pt-4">
                        <ResponsiveContainer width="100%" height={240}>
                            <BarChart data={enrollmentByClass} margin={{ top: 4, right: 12, left: -14, bottom: 0 }} barCategoryGap="28%">
                                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                                <XAxis dataKey="c" tick={{ fontSize: 11, fill: 'var(--faint)' }} tickLine={false} axisLine={false} />
                                <YAxis tick={{ fontSize: 11, fill: 'var(--faint)' }} tickLine={false} axisLine={false} />
                                <Tooltip {...chartTooltip} cursor={{ fill: 'var(--surface-2)' }} />
                                <Legend wrapperStyle={{ fontSize: 12, color: 'var(--muted)' }} iconType="circle" />
                                <Bar dataKey="boys" name="Boys" fill="#5343e0" radius={[5, 5, 0, 0]} maxBarSize={26} />
                                <Bar dataKey="girls" name="Girls" fill="#f59e0b" radius={[5, 5, 0, 0]} maxBarSize={26} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Fee collection" subtitle="Monthly, last 6 months" />
                    <div className="px-2 pb-3 pt-4">
                        <ResponsiveContainer width="100%" height={240}>
                            <BarChart data={collectionByMonth} margin={{ top: 4, right: 12, left: -6, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
                                <XAxis dataKey="m" tick={{ fontSize: 11, fill: 'var(--faint)' }} tickLine={false} axisLine={false} />
                                <YAxis hide />
                                <Tooltip {...chartTooltip} cursor={{ fill: 'var(--surface-2)' }} formatter={(v: number) => money(v)} />
                                <Bar dataKey="amount" name="Collected" fill="#10b981" radius={[5, 5, 0, 0]} maxBarSize={30} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </Card>
            </div>

            {/* activity row */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader title="Recent admissions" subtitle="Latest applications and enrolments" action={<Button variant="ghost" className="text-brand-600 dark:text-brand-400">View all</Button>} />
                    <div className="mt-3 overflow-x-auto">
                        <table className="w-full min-w-[480px] text-left text-[13.5px]">
                            <thead>
                                <tr className="border-y border-border text-[11px] uppercase tracking-wide text-faint">
                                    <th className="px-5 py-2.5 font-semibold">Student</th>
                                    <th className="px-5 py-2.5 font-semibold">Class</th>
                                    <th className="px-5 py-2.5 font-semibold">Roll</th>
                                    <th className="px-5 py-2.5 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentAdmissions.map((r) => (
                                    <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="px-5 py-3 font-medium text-fg">{r.name}</td>
                                        <td className="px-5 py-3 text-muted">{r.cls}</td>
                                        <td className="tnum px-5 py-3 text-muted">{r.roll}</td>
                                        <td className="px-5 py-3">
                                            <Badge tone={r.status === 'Admitted' ? 'success' : r.status === 'Pending' ? 'warning' : 'sky'}>
                                                {r.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <div className="space-y-4">
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg">Notices</h3>
                        <ul className="mt-3 space-y-3">
                            {notices.map((n) => (
                                <li key={n.id} className="flex items-start gap-3">
                                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />
                                    <div className="min-w-0">
                                        <div className="text-[13px] font-medium leading-snug text-fg">{n.title}</div>
                                        <div className="mt-1 flex items-center gap-2">
                                            <Badge tone={n.tone}>{n.tag}</Badge>
                                            <span className="text-[11.5px] text-faint">{n.when}</span>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card className="p-5">
                        <h3 className="text-[15px] font-semibold text-fg">Upcoming exams</h3>
                        <ul className="mt-3 space-y-2.5">
                            {upcomingExams.map((e) => (
                                <li key={e.id} className="flex items-center gap-3 rounded-xl border border-border bg-surface-2/40 p-2.5">
                                    <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-surface text-center">
                                        <span className="font-display text-[13px] font-bold leading-none text-brand-600 dark:text-brand-400">
                                            {e.date.split(' ')[0]}
                                        </span>
                                        <span className="text-[9px] uppercase text-faint">{e.date.split(' ')[1]}</span>
                                    </div>
                                    <div className="min-w-0">
                                        <div className="truncate text-[13px] font-medium text-fg">{e.name}</div>
                                        <div className="text-[11.5px] text-faint">{e.subject} · Room {e.room}</div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            </div>
        </div>
    );
}
