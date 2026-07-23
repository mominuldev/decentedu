import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Pencil, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { listSetup, createSetup, updateSetup, deleteSetup, type SetupRow } from './api';

export interface FieldDef {
    name: string;
    label: string;
    type: 'text' | 'number' | 'checkbox' | 'select';
    required?: boolean;
    placeholder?: string;
    options?: { value: string; label: string }[];
}

const baseFields: FieldDef[] = [
    { name: 'name', label: 'Name', type: 'text', required: true },
    { name: 'name_bn', label: 'Name (বাংলা)', type: 'text' },
];

export function SetupResource({
    resource, singular, extraFields = [],
}: {
    resource: string;
    singular: string;
    extraFields?: FieldDef[];
}) {
    const qc = useQueryClient();
    const key = ['exam-setup', resource];

    const fields = useMemo<FieldDef[]>(
        () => [...baseFields, ...extraFields, { name: 'serial', label: 'Serial', type: 'number' }],
        [extraFields],
    );

    const { data: rows = [], isLoading } = useQuery({ queryKey: key, queryFn: () => listSetup(resource) });

    const [editing, setEditing] = useState<SetupRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<SetupRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: key });

    const del = useMutation({
        mutationFn: (id: number) => deleteSetup(resource, id),
        onSuccess: () => { invalidate(); setDeleting(null); },
    });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">{singular} list</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} record{rows.length === 1 ? '' : 's'} in this branch</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add {singular.toLowerCase()}</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted">
                        <Loader2 size={18} className="animate-spin" /> Loading…
                    </div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No {singular.toLowerCase()} yet</p>
                        <p className="text-[13px] text-muted">Add your first {singular.toLowerCase()} to get started.</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                {extraFields.map((f) => <th key={f.name} className="px-5 py-2.5 font-semibold">{f.label}</th>)}
                                <th className="px-5 py-2.5 font-semibold">Serial</th>
                                <th className="px-5 py-2.5 font-semibold">Status</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3">
                                        <div className="font-medium text-fg">{r.name}</div>
                                        {r.name_bn ? <div className="text-[12px] text-faint">{r.name_bn as string}</div> : null}
                                    </td>
                                    {extraFields.map((f) => (
                                        <td key={f.name} className="px-5 py-3 text-muted">
                                            {f.type === 'checkbox'
                                                ? (r[f.name] ? <Badge tone="brand">Yes</Badge> : <span className="text-faint">—</span>)
                                                : f.type === 'select'
                                                    ? (f.options?.find((o) => o.value === r[f.name])?.label ?? (r[f.name] as string) ?? '—')
                                                    : ((r[f.name] as string) || '—')}
                                        </td>
                                    ))}
                                    <td className="tnum px-5 py-3 text-muted">{r.serial}</td>
                                    <td className="px-5 py-3">
                                        <Badge tone={r.status ? 'success' : 'neutral'}>{r.status ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setEditing(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Edit">
                                                <Pencil size={16} />
                                            </button>
                                            <button onClick={() => setDeleting(r)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete">
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <SetupForm
                    resource={resource}
                    singular={singular}
                    fields={fields}
                    row={editing}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={() => { invalidate(); setCreating(false); setEditing(null); }}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && del.mutate(deleting.id)}
                busy={del.isPending}
                title={`Delete ${singular.toLowerCase()}`}
                message={`Are you sure you want to delete "${deleting?.name}"? This can be restored by an administrator.`}
            />
        </Card>
    );
}

function defaults(fields: FieldDef[], row: SetupRow | null): Record<string, unknown> {
    const out: Record<string, unknown> = { status: row ? row.status : true };
    for (const f of fields) {
        out[f.name] = row
            ? (row[f.name] ?? (f.type === 'checkbox' ? false : ''))
            : (f.type === 'checkbox' ? false : f.type === 'number' ? 0 : f.type === 'select' ? (f.options?.[0]?.value ?? '') : '');
    }

    return out;
}

function SetupForm({
    resource, singular, fields, row, onClose, onSaved,
}: {
    resource: string;
    singular: string;
    fields: FieldDef[];
    row: SetupRow | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const [form, setForm] = useState<Record<string, unknown>>(() => defaults(fields, row));
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => (row ? updateSetup(resource, row.id, form) : createSetup(resource, form)),
        onSuccess: onSaved,
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const set = (k: string, v: unknown) => setForm((f) => ({ ...f, [k]: v }));
    const inputCls = (hasError: boolean) => cn(
        'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25',
        hasError ? 'border-rose-400' : 'border-border-strong',
    );

    return (
        <Modal
            open
            onClose={onClose}
            title={row ? `Edit ${singular.toLowerCase()}` : `Add ${singular.toLowerCase()}`}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                    <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                        {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null}
                        {row ? 'Save changes' : `Create ${singular.toLowerCase()}`}
                    </Button>
                </>
            }
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                {fields.map((f) => (
                    <div key={f.name}>
                        {f.type === 'checkbox' ? (
                            <label className="flex cursor-pointer select-none items-center gap-2.5 text-[13.5px] text-fg">
                                <input type="checkbox" checked={!!form[f.name]} onChange={(e) => set(f.name, e.target.checked)}
                                    className="h-4 w-4 rounded border-border-strong text-brand-600 focus:ring-brand-500" />
                                {f.label}
                            </label>
                        ) : f.type === 'select' ? (
                            <>
                                <label className="mb-1.5 block text-[13px] font-medium text-fg">
                                    {f.label}{f.required && <span className="text-rose-500"> *</span>}
                                </label>
                                <select value={String(form[f.name] ?? '')} onChange={(e) => set(f.name, e.target.value)} className={inputCls(!!errors[f.name])}>
                                    {f.options?.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </select>
                            </>
                        ) : (
                            <>
                                <label className="mb-1.5 block text-[13px] font-medium text-fg">
                                    {f.label}{f.required && <span className="text-rose-500"> *</span>}
                                </label>
                                <input
                                    type={f.type === 'number' ? 'number' : 'text'}
                                    value={String(form[f.name] ?? '')}
                                    onChange={(e) => set(f.name, f.type === 'number' ? Number(e.target.value) : e.target.value)}
                                    placeholder={f.placeholder}
                                    className={inputCls(!!errors[f.name])}
                                />
                            </>
                        )}
                        {errors[f.name] && <p className="mt-1.5 text-[12px] text-rose-500">{errors[f.name][0]}</p>}
                    </div>
                ))}

                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Status</label>
                    <select
                        value={form.status ? '1' : '0'} onChange={(e) => set('status', e.target.value === '1')}
                        className="w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </Modal>
    );
}
