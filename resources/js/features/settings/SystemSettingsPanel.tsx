import { useQuery } from '@tanstack/react-query';
import { Loader2, Server, Activity, CheckCircle2, Users, GraduationCap, Briefcase } from 'lucide-react';
import { Card, Badge } from '@/components/ui';
import { fetchSystemSettings } from './api';

export function SystemSettingsPanel() {
    const { data: sys, isLoading, error } = useQuery({
        queryKey: ['settings', 'system'],
        queryFn: fetchSystemSettings,
    });

    if (isLoading) {
        return (
            <Card className="flex items-center justify-center gap-2 py-20 text-muted">
                <Loader2 size={18} className="animate-spin" /> Loading system information…
            </Card>
        );
    }

    if (error || !sys) {
        return (
            <Card className="px-5 py-8 text-center text-rose-500">
                Failed to load system diagnostics.
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Environment Overview */}
            <div className="grid gap-4 sm:grid-cols-3">
                <Card className="px-5 py-4">
                    <div className="flex items-center justify-between">
                        <span className="text-[13px] font-medium text-muted">Framework</span>
                        <Badge tone="brand">v{sys.laravel_version}</Badge>
                    </div>
                    <p className="mt-2 text-[20px] font-bold text-fg">Laravel Framework</p>
                    <p className="mt-0.5 text-[12px] text-faint">PHP {sys.php_version}</p>
                </Card>

                <Card className="px-5 py-4">
                    <div className="flex items-center justify-between">
                        <span className="text-[13px] font-medium text-muted">Environment</span>
                        <Badge tone={sys.environment === 'production' ? 'success' : 'warning'}>{sys.environment}</Badge>
                    </div>
                    <p className="mt-2 text-[20px] font-bold text-fg">{sys.db_driver.toUpperCase()} Engine</p>
                    <p className="mt-0.5 text-[12px] text-faint">Cache: {sys.cache_driver} · Session: {sys.session_driver}</p>
                </Card>

                <Card className="px-5 py-4">
                    <div className="flex items-center justify-between">
                        <span className="text-[13px] font-medium text-muted">Active Branch Context</span>
                        <Badge tone="sky">{sys.active_branch.code ?? 'DEFAULT'}</Badge>
                    </div>
                    <p className="mt-2 text-[20px] font-bold text-fg truncate">{sys.active_branch.name ?? 'Branch #1'}</p>
                    <p className="mt-0.5 text-[12px] text-faint">ID: {sys.active_branch.id}</p>
                </Card>
            </div>

            {/* Quick Record Counters */}
            <Card>
                <div className="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <Activity size={18} />
                    </div>
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">Database Records Summary</h3>
                        <p className="text-[12.5px] text-muted">Total records across active modules in DecentEdu.</p>
                    </div>
                </div>

                <div className="grid divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div className="flex items-center gap-4 px-6 py-5">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            <GraduationCap size={22} />
                        </div>
                        <div>
                            <p className="text-[24px] font-bold text-fg">{sys.counts.students.toLocaleString()}</p>
                            <p className="text-[13px] font-medium text-muted">Enrolled Students</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4 px-6 py-5">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <Briefcase size={22} />
                        </div>
                        <div>
                            <p className="text-[24px] font-bold text-fg">{sys.counts.employees.toLocaleString()}</p>
                            <p className="text-[13px] font-medium text-muted">Staff & Employees</p>
                        </div>
                    </div>

                    <div className="flex items-center gap-4 px-6 py-5">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <Users size={22} />
                        </div>
                        <div>
                            <p className="text-[24px] font-bold text-fg">{sys.counts.users.toLocaleString()}</p>
                            <p className="text-[13px] font-medium text-muted">System User Accounts</p>
                        </div>
                    </div>
                </div>
            </Card>

            {/* Diagnostics Table */}
            <Card>
                <div className="flex items-center gap-3 px-5 py-4 border-b border-border">
                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                        <Server size={18} />
                    </div>
                    <div>
                        <h3 className="text-[15px] font-semibold text-fg">Server & Runtime Info</h3>
                        <p className="text-[12.5px] text-muted">Configuration settings, drivers, and server clock.</p>
                    </div>
                </div>

                <div className="divide-y divide-border">
                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">PHP Version</span>
                        <span className="font-mono text-fg">{sys.php_version}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">Laravel Framework</span>
                        <span className="font-mono text-fg">v{sys.laravel_version}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">Database Engine</span>
                        <span className="font-mono text-fg uppercase">{sys.db_driver}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">Cache Store</span>
                        <span className="font-mono text-fg">{sys.cache_driver}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">Session Driver</span>
                        <span className="font-mono text-fg">{sys.session_driver}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">System Timezone</span>
                        <span className="font-mono text-fg">{sys.timezone}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">Server Time</span>
                        <span className="font-mono text-fg">{new Date(sys.server_time).toLocaleString()}</span>
                    </div>

                    <div className="flex items-center justify-between px-5 py-3 text-[13.5px]">
                        <span className="font-medium text-muted">Security & Audit Status</span>
                        <Badge tone="success" className="gap-1">
                            <CheckCircle2 size={12} /> Active & Monitored
                        </Badge>
                    </div>
                </div>
            </Card>
        </div>
    );
}
