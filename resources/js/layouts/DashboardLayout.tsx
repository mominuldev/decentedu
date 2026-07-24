import { useState, type ReactNode } from 'react';
import { NavLink } from 'react-router-dom';
import {
    LayoutDashboard, UserPlus, GraduationCap, BookOpen, Users, CalendarCheck,
    CalendarClock, ClipboardList, Trophy, Wallet, Landmark, MessageSquare,
    IdCard, Globe, ShieldCheck, Settings, Search, Bell, Menu, X, Sun, Moon,
    ChevronDown, LogOut, History,
} from 'lucide-react';
import { cn } from '@/lib/cn';
import { useTheme } from '@/app/theme';
import { useAuth } from '@/features/auth/AuthProvider';
import { IconButton } from '@/components/ui';

type Item = { to: string; label: string; icon: typeof LayoutDashboard };
type Group = { heading?: string; items: Item[] };

const nav: Group[] = [
    { items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard }] },
    {
        heading: 'Academic',
        items: [
            { to: '/admissions', label: 'Admissions', icon: UserPlus },
            { to: '/students', label: 'Students', icon: GraduationCap },
            { to: '/academic', label: 'Academic Setup', icon: BookOpen },
        ],
    },
    {
        heading: 'People',
        items: [
            { to: '/hr', label: 'HR & Staff', icon: Users },
            { to: '/attendance', label: 'Attendance', icon: CalendarCheck },
        ],
    },
    {
        heading: 'Assessment',
        items: [
            { to: '/routines', label: 'Routines', icon: CalendarClock },
            { to: '/exams', label: 'Examinations', icon: ClipboardList },
            { to: '/results', label: 'Results', icon: Trophy },
        ],
    },
    {
        heading: 'Finance',
        items: [
            { to: '/fees', label: 'Fees', icon: Wallet },
            { to: '/accounting', label: 'Accounting', icon: Landmark },
        ],
    },
    {
        heading: 'Engagement',
        items: [
            { to: '/messaging', label: 'SMS & Notices', icon: MessageSquare },
            { to: '/credentials', label: 'Credentials', icon: IdCard },
            { to: '/website', label: 'Website', icon: Globe },
        ],
    },
    {
        heading: 'System',
        items: [
            { to: '/users', label: 'Users & Roles', icon: ShieldCheck },
            { to: '/audit-log', label: 'Audit Log', icon: History },
            { to: '/settings', label: 'Settings', icon: Settings },
        ],
    },
];

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    return (
        <div className="flex h-full flex-col">
            {/* brand */}
            <div className="flex h-16 items-center gap-2.5 px-5">
                <div className="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-white shadow-[var(--shadow-soft)]">
                    <GraduationCap size={20} strokeWidth={2.4} />
                </div>
                <div className="leading-tight">
                    <div className="font-display text-[17px] font-extrabold tracking-tight text-fg">
                        Decent<span className="text-brand-600 dark:text-brand-400">Edu</span>
                    </div>
                    <div className="text-[10.5px] font-medium uppercase tracking-[0.14em] text-faint">School Suite</div>
                </div>
            </div>

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 pb-6 pt-2">
                {nav.map((group, gi) => (
                    <div key={gi}>
                        {group.heading && (
                            <div className="px-3 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.12em] text-faint">
                                {group.heading}
                            </div>
                        )}
                        <div className="space-y-0.5">
                            {group.items.map((it) => (
                                <NavLink
                                    key={it.to}
                                    to={it.to}
                                    end={it.to === '/'}
                                    onClick={onNavigate}
                                    className={({ isActive }) =>
                                        cn(
                                            'group flex items-center gap-3 rounded-xl px-3 py-2 text-[13.5px] font-medium transition-colors',
                                            isActive
                                                ? 'bg-brand-600 text-white shadow-[var(--shadow-soft)]'
                                                : 'text-muted hover:bg-surface-2 hover:text-fg',
                                        )
                                    }
                                >
                                    {({ isActive }) => (
                                        <>
                                            <it.icon
                                                size={18}
                                                strokeWidth={2.1}
                                                className={cn(isActive ? 'text-white' : 'text-faint group-hover:text-fg')}
                                            />
                                            {it.label}
                                        </>
                                    )}
                                </NavLink>
                            ))}
                        </div>
                    </div>
                ))}
            </nav>

            <UserFooter />
        </div>
    );
}

function initials(name: string) {
    return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

function UserFooter() {
    const { session, logout } = useAuth();
    const user = session?.user;
    return (
        <div className="border-t border-border px-3 py-3">
            <div className="flex items-center gap-3 rounded-xl px-2 py-2">
                <div className="grid h-9 w-9 place-items-center rounded-lg bg-brand-100 text-[12px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                    {user ? initials(user.name) : '—'}
                </div>
                <div className="min-w-0 flex-1 leading-tight">
                    <div className="truncate text-[13px] font-semibold text-fg">{user?.name ?? 'User'}</div>
                    <div className="truncate text-[11.5px] text-faint">{user?.role ?? 'Member'}</div>
                </div>
                <IconButton aria-label="Sign out" onClick={() => logout()}><LogOut size={17} /></IconButton>
            </div>
        </div>
    );
}

function BranchSwitcher() {
    const { session, switchBranch } = useAuth();
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const branches = session?.branches ?? [];
    const active = session?.active_branch;

    async function choose(id: number) {
        if (id === active?.id) { setOpen(false); return; }
        setBusy(true);
        try { await switchBranch(id); } finally { setBusy(false); setOpen(false); }
    }

    return (
        <div className="relative">
            <button
                onClick={() => setOpen((o) => !o)}
                disabled={busy}
                data-testid="branch-switcher"
                className="flex items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 text-[13px] font-semibold text-fg hover:bg-surface-2 disabled:opacity-60"
            >
                <span className="grid h-5 w-5 place-items-center rounded-md bg-brand-100 text-[10px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                    {active?.name.slice(0, 1) ?? '—'}
                </span>
                <span className="hidden max-w-[160px] truncate sm:block">{active?.name ?? 'Select branch'}</span>
                <ChevronDown size={15} className="text-faint" />
            </button>
            {open && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 z-20 mt-2 w-64 rounded-2xl border border-border bg-surface p-1.5 shadow-[var(--shadow-pop)]">
                        <div className="px-3 py-1.5 text-[10.5px] font-semibold uppercase tracking-[0.1em] text-faint">
                            Switch branch
                        </div>
                        {branches.map((b) => (
                            <button
                                key={b.id}
                                onClick={() => choose(b.id)}
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-[13px] hover:bg-surface-2',
                                    b.id === active?.id ? 'font-semibold text-fg' : 'text-muted',
                                )}
                            >
                                <span className="grid h-5 w-5 place-items-center rounded-md bg-surface-2 text-[10px] font-bold text-muted">
                                    {b.name.slice(0, 1)}
                                </span>
                                <span className="truncate">{b.name}</span>
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function Topbar({ onMenu }: { onMenu: () => void }) {
    const { theme, toggle } = useTheme();
    return (
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-bg/80 px-4 backdrop-blur-md sm:px-6">
            <IconButton className="lg:hidden" aria-label="Open menu" onClick={onMenu}><Menu size={20} /></IconButton>

            <div className="hidden items-center gap-2 rounded-xl border border-border bg-surface px-3 py-2 md:flex md:w-72">
                <Search size={16} className="text-faint" />
                <input
                    placeholder="Search students, staff, invoices…"
                    className="w-full bg-transparent text-[13px] text-fg outline-none placeholder:text-faint"
                />
            </div>

            <div className="ml-auto flex items-center gap-2">
                <span className="hidden rounded-lg bg-surface-2 px-2.5 py-1 text-[11.5px] font-semibold text-muted sm:block">
                    Session <span className="text-fg">2026</span>
                </span>
                <BranchSwitcher />
                <IconButton aria-label="Notifications" className="relative">
                    <Bell size={19} />
                    <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-bg" />
                </IconButton>
                <IconButton aria-label="Toggle theme" onClick={toggle}>
                    {theme === 'dark' ? <Sun size={19} /> : <Moon size={19} />}
                </IconButton>
            </div>
        </header>
    );
}

export function DashboardLayout({ children }: { children: ReactNode }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    return (
        <div className="min-h-screen bg-bg">
            {/* desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-border bg-surface lg:block">
                <Sidebar />
            </aside>

            {/* mobile drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" onClick={() => setMobileOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-72 border-r border-border bg-surface">
                        <IconButton className="absolute right-3 top-4" aria-label="Close" onClick={() => setMobileOpen(false)}>
                            <X size={20} />
                        </IconButton>
                        <Sidebar onNavigate={() => setMobileOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                <Topbar onMenu={() => setMobileOpen(true)} />
                <main className="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">{children}</main>
            </div>
        </div>
    );
}
