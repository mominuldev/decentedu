import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, ShieldCheck } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { useAuth } from '@/features/auth/AuthProvider';
import { listRoles, listPermissions, updateRolePermissions } from './api';

const LABELS: Record<string, string> = {
    'academic.manage': 'Academic setup',
    'students.manage': 'Students',
    'hr.manage': 'HR',
    'routines.manage': 'Routines',
    'attendance.manage': 'Attendance',
    'examinations.manage': 'Examinations & results',
    'fees.manage': 'Fees',
    'accounting.manage': 'Accounting',
    'messaging.manage': 'SMS & notices',
    'credentials.manage': 'Credentials & ID cards',
    'cms.manage': 'Website CMS',
    'reports.view': 'Reports',
    'users.manage': 'Users & roles',
    'audit.view': 'Audit log',
};

export function RolesPanel() {
    const qc = useQueryClient();
    const { can } = useAuth();
    const { data: roles = [], isLoading } = useQuery({ queryKey: ['roles'], queryFn: listRoles });
    const { data: permissions = [] } = useQuery({ queryKey: ['permissions'], queryFn: listPermissions });
    const [pending, setPending] = useState<Record<number, string[]>>({});
    const manage = can('users.manage');

    const save = useMutation({
        mutationFn: ({ id, perms }: { id: number; perms: string[] }) => updateRolePermissions(id, perms),
        onSuccess: () => qc.invalidateQueries({ queryKey: ['roles'] }),
    });

    if (isLoading) {
        return <Card><div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div></Card>;
    }

    return (
        <Card>
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Roles & permissions</h3>
                <p className="text-[12.5px] text-muted">Toggle which modules each role can access. Super Admin always has full access.</p>
            </div>
            <div className="overflow-x-auto border-t border-border">
                <table className="w-full min-w-[720px] text-left text-[13.5px]">
                    <thead>
                        <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                            <th className="px-5 py-2.5 font-semibold">Module</th>
                            {roles.map((r) => (
                                <th key={r.id} className="px-5 py-2.5 text-center font-semibold">{r.name}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {permissions.map((perm) => (
                            <tr key={perm} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                <td className="px-5 py-3 font-medium text-fg">{LABELS[perm] ?? perm}</td>
                                {roles.map((r) => {
                                    const isSuper = r.name === 'Super Admin';
                                    const current = pending[r.id] ?? r.permissions;
                                    const checked = isSuper || current.includes(perm);
                                    return (
                                        <td key={r.id} className="px-5 py-3 text-center">
                                            {isSuper ? (
                                                <ShieldCheck size={16} className="mx-auto text-emerald-500" />
                                            ) : (
                                                <input
                                                    type="checkbox" checked={checked} disabled={!manage}
                                                    onChange={(e) => {
                                                        const base = pending[r.id] ?? r.permissions;
                                                        const next = e.target.checked ? [...base, perm] : base.filter((p) => p !== perm);
                                                        setPending((p) => ({ ...p, [r.id]: next }));
                                                    }}
                                                    className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500/25"
                                                />
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {manage && Object.keys(pending).length > 0 && (
                <div className="flex items-center justify-end gap-2 border-t border-border px-5 py-3.5">
                    {save.isSuccess && <Badge tone="success">Saved</Badge>}
                    <Button variant="outline" onClick={() => setPending({})}>Discard</Button>
                    <Button
                        onClick={() => {
                            Object.entries(pending).forEach(([id, perms]) => save.mutate({ id: Number(id), perms }));
                            setPending({});
                        }}
                        disabled={save.isPending}
                    >
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Save changes
                    </Button>
                </div>
            )}
        </Card>
    );
}
