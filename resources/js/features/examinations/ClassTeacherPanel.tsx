import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Pencil, Trash2 } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listClassConfigs } from '@/features/academic/api';
import { listEmployees } from '@/features/hr/api';
import { listClassTeacherConfigs, saveClassTeacherConfig, deleteClassTeacherConfig, type ClassTeacherConfigRow } from './api';

export function ClassTeacherPanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: configs = [], isLoading } = useQuery({ queryKey: ['class-teacher-configs'], queryFn: listClassTeacherConfigs });
    const qc = useQueryClient();

    const [editing, setEditing] = useState<{ classConfigId: number; row: ClassTeacherConfigRow | null } | null>(null);
    const [deleting, setDeleting] = useState<ClassTeacherConfigRow | null>(null);
    const invalidate = () => qc.invalidateQueries({ queryKey: ['class-teacher-configs'] });

    const del = useMutation({
        mutationFn: (id: number) => deleteClassTeacherConfig(id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="px-5 py-4">
                <h3 className="text-[15px] font-semibold text-fg">Class teacher</h3>
                <p className="text-[12.5px] text-muted">The signing class teacher for each section — appears on marksheets/admit cards</p>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : (
                    <table className="w-full min-w-[520px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Class</th>
                                <th className="px-5 py-2.5 font-semibold">Class teacher</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {classConfigs.map((c) => {
                                const config = configs.find((cfg) => cfg.class_config_id === c.id);

                                return (
                                    <tr key={c.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                        <td className="px-5 py-3 font-medium text-fg">{c.label}</td>
                                        <td className="px-5 py-3 text-muted">{config?.employee_name ?? '—'}</td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-1">
                                                <button onClick={() => setEditing({ classConfigId: c.id, row: config ?? null })} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit"><Pencil size={16} /></button>
                                                {config && <button onClick={() => setDeleting(config)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {editing && (
                <ClassTeacherForm
                    classConfigId={editing.classConfigId}
                    label={classConfigs.find((c) => c.id === editing.classConfigId)?.label ?? ''}
                    row={editing.row}
                    onClose={() => setEditing(null)}
                    onSaved={() => { invalidate(); setEditing(null); }}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title="Remove class teacher"
                message="Remove the assigned class teacher for this section?"
            />
        </Card>
    );
}

function ClassTeacherForm({
    classConfigId, label, row, onClose, onSaved,
}: {
    classConfigId: number;
    label: string;
    row: ClassTeacherConfigRow | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const { data: employees } = useQuery({
        queryKey: ['employees-for-teacher-select'],
        queryFn: () => listEmployees({ teachers_only: true, per_page: 200 }),
    });
    const [employeeId, setEmployeeId] = useState(row?.employee_id ?? 0);
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => saveClassTeacherConfig({ class_config_id: classConfigId, employee_id: employeeId }),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    return (
        <Modal
            open onClose={onClose}
            title={`Class teacher — ${label}`}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !employeeId}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Save
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <label className="mb-1.5 block text-[13px] font-medium text-fg">Teacher <span className="text-rose-500">*</span></label>
            <select value={employeeId} onChange={(e) => setEmployeeId(Number(e.target.value))}
                className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
                <option value={0} disabled>Select teacher</option>
                {employees?.data.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
            </select>
        </Modal>
    );
}
