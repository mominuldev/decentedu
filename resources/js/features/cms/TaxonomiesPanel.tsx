import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Tags, Pencil, ChevronRight } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import {
    listTaxonomies, createTaxonomy, updateTaxonomy, deleteTaxonomy, getTaxonomyTerms, createTerm, updateTerm, deleteTerm,
    inputCls, TAXONOMY_OBJECT_TYPES, type TaxonomyObjectType, type TaxonomyRow, type TermRow, type TermPayload,
} from './api';
import { Field, Loading, Empty } from './PagesPanel';

export function TaxonomiesPanel() {
    const qc = useQueryClient();
    const { data: taxonomies = [], isLoading } = useQuery({ queryKey: ['cms-taxonomies'], queryFn: listTaxonomies });
    const [selected, setSelected] = useState<TaxonomyRow | null>(null);
    const [creatingTax, setCreatingTax] = useState(false);
    const [editingTax, setEditingTax] = useState<TaxonomyRow | null>(null);
    const [deletingTax, setDeletingTax] = useState<TaxonomyRow | null>(null);

    const del = useMutation({ mutationFn: (id: number) => deleteTaxonomy(id), onSuccess: () => { qc.invalidateQueries({ queryKey: ['cms-taxonomies'] }); setDeletingTax(null); setSelected(null); } });
    const afterSave = (t: TaxonomyRow) => { qc.invalidateQueries({ queryKey: ['cms-taxonomies'] }); if (selected?.id === t.id) setSelected(t); setCreatingTax(false); setEditingTax(null); };

    return (
        <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
            <Card>
                <div className="flex items-center justify-between px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Taxonomies</h3>
                    <Button size="sm" onClick={() => setCreatingTax(true)}><Plus size={15} /></Button>
                </div>
                <div className="border-t border-border">
                    {isLoading ? <Loading /> : taxonomies.length === 0 ? <Empty label="None yet" /> : taxonomies.map((t) => (
                        <button key={t.id} onClick={() => setSelected(t)}
                            className={`flex w-full items-center justify-between border-b border-border px-5 py-3 text-left last:border-0 hover:bg-surface-2/50 ${selected?.id === t.id ? 'bg-surface-2/60' : ''}`}>
                            <span className="min-w-0">
                                <span className="flex items-center gap-2 text-[13.5px] font-medium text-fg"><Tags size={15} className="text-faint" /> {t.name}</span>
                                <span className="mt-0.5 block truncate text-[11.5px] text-muted">{scopeLabel(t.object_types)}</span>
                            </span>
                            <span className="flex items-center gap-2"><Badge size="sm">{t.terms_count}</Badge><ChevronRight size={15} className="text-faint" /></span>
                        </button>
                    ))}
                </div>
            </Card>

            {selected ? (
                <TermsManager taxonomy={selected} onEditTaxonomy={() => setEditingTax(selected)} onDeleteTaxonomy={() => setDeletingTax(selected)} />
            ) : (
                <Card><Empty label="Select a taxonomy to manage its terms" /></Card>
            )}

            {(creatingTax || editingTax) && (
                <TaxonomyForm taxonomy={editingTax} onClose={() => { setCreatingTax(false); setEditingTax(null); }} onSaved={afterSave} />
            )}
            <ConfirmDialog open={!!deletingTax} onClose={() => setDeletingTax(null)} onConfirm={() => deletingTax && del.mutate(deletingTax.id)} busy={del.isPending}
                title="Delete taxonomy" message={`Delete "${deletingTax?.name}" and all its terms?`} />
        </div>
    );
}

function scopeLabel(types: TaxonomyObjectType[] | null): string {
    if (!types || types.length === 0) return 'All content';
    return TAXONOMY_OBJECT_TYPES.filter((o) => types.includes(o.value)).map((o) => o.label).join(', ');
}

function TaxonomyForm({ taxonomy, onClose, onSaved }: { taxonomy: TaxonomyRow | null; onClose: () => void; onSaved: (t: TaxonomyRow) => void }) {
    const [name, setName] = useState(taxonomy?.name ?? '');
    const [hierarchical, setHierarchical] = useState(taxonomy?.hierarchical ?? true);
    const [objectTypes, setObjectTypes] = useState<TaxonomyObjectType[]>(taxonomy?.object_types ?? ['post']);
    const [error, setError] = useState<string | null>(null);
    const toggleType = (value: TaxonomyObjectType) =>
        setObjectTypes((prev) => (prev.includes(value) ? prev.filter((v) => v !== value) : [...prev, value]));
    const save = useMutation({
        mutationFn: () => {
            const payload = { name, hierarchical, object_types: objectTypes };
            return taxonomy ? updateTaxonomy(taxonomy.id, payload) : createTaxonomy(payload);
        },
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });
    return (
        <Modal open onClose={onClose} title={taxonomy ? 'Edit taxonomy' : 'New taxonomy'}
            footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => save.mutate()} disabled={save.isPending || objectTypes.length === 0}>{taxonomy ? 'Save' : 'Create'}</Button></>}>
            <div className="space-y-4">
                {error && <p className="text-[13px] text-rose-600">{error}</p>}
                <Field label="Name"><input className={inputCls} value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Category" /></Field>
                <Field label="Assign to">
                    <div className="space-y-2">
                        {TAXONOMY_OBJECT_TYPES.map((o) => (
                            <label key={o.value} className="flex items-center gap-2 text-[13px] text-fg">
                                <input type="checkbox" checked={objectTypes.includes(o.value)} onChange={() => toggleType(o.value)} /> {o.label}
                            </label>
                        ))}
                        <p className="text-[12px] text-muted">This taxonomy will only appear on the selected content editors.</p>
                    </div>
                </Field>
                <label className="flex items-center gap-2 text-[13px] text-fg"><input type="checkbox" checked={hierarchical} onChange={(e) => setHierarchical(e.target.checked)} /> Hierarchical (terms can nest)</label>
            </div>
        </Modal>
    );
}

function TermsManager({ taxonomy, onEditTaxonomy, onDeleteTaxonomy }: { taxonomy: TaxonomyRow; onEditTaxonomy: () => void; onDeleteTaxonomy: () => void }) {
    const qc = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['cms-terms', taxonomy.id], queryFn: () => getTaxonomyTerms(taxonomy.id) });
    const [editing, setEditing] = useState<TermRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<TermRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-terms', taxonomy.id] });
    const del = useMutation({ mutationFn: (id: number) => deleteTerm(id), onSuccess: () => { invalidate(); qc.invalidateQueries({ queryKey: ['cms-taxonomies'] }); setDeleting(null); } });

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">{taxonomy.name} terms</h3>
                    <p className="text-[12.5px] text-muted">slug: {taxonomy.slug} · {scopeLabel(taxonomy.object_types)}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button size="sm" variant="outline" onClick={onEditTaxonomy}><Pencil size={15} /> Edit</Button>
                    <Button size="sm" variant="outline" className="text-rose-600" onClick={onDeleteTaxonomy}><Trash2 size={15} /></Button>
                    <Button size="sm" onClick={() => setCreating(true)}><Plus size={15} /> Add term</Button>
                </div>
            </div>
            <div className="border-t border-border">
                {isLoading ? <Loading /> : (data?.terms.length ?? 0) === 0 ? <Empty label="No terms yet" /> : (
                    <table className="w-full text-left text-[13.5px]">
                        <thead><tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                            <th className="px-5 py-2.5 font-semibold">Name</th><th className="px-5 py-2.5 font-semibold">Slug</th>
                            <th className="px-5 py-2.5 font-semibold">Posts</th><th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                        </tr></thead>
                        <tbody>
                            {data?.terms.map((t) => (
                                <tr key={t.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="px-5 py-3 font-medium text-fg">{t.parent ? <span className="text-faint">↳ </span> : ''}{t.name}</td>
                                    <td className="px-5 py-3 text-muted">{t.slug}</td>
                                    <td className="px-5 py-3 text-muted">{t.posts_count}</td>
                                    <td className="px-5 py-3"><div className="flex justify-end gap-1">
                                        <button onClick={() => setEditing(t)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"><Pencil size={16} /></button>
                                        <button onClick={() => setDeleting(t)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"><Trash2 size={16} /></button>
                                    </div></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {(creating || editing) && (
                <TermForm taxonomyId={taxonomy.id} term={editing} parents={data?.terms ?? []}
                    onClose={() => { setCreating(false); setEditing(null); }}
                    onSaved={() => { invalidate(); qc.invalidateQueries({ queryKey: ['cms-taxonomies'] }); setCreating(false); setEditing(null); }} />
            )}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending}
                title="Delete term" message={`Delete "${deleting?.name}"?`} />
        </Card>
    );
}

function TermForm({ taxonomyId, term, parents, onClose, onSaved }: { taxonomyId: number; term: TermRow | null; parents: TermRow[]; onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState<TermPayload>({ taxonomy_id: taxonomyId, name: term?.name ?? '', slug: term?.slug ?? '', parent_id: term?.parent_id ?? null, description: term?.description ?? '' });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const save = useMutation({
        mutationFn: () => term ? updateTerm(term.id, form) : createTerm(form),
        onSuccess: onSaved,
        onError: (e) => { const err = toApiError(e); setError(err.message); setErrors(err.errors ?? {}); },
    });
    const set = (p: Partial<TermPayload>) => setForm((f) => ({ ...f, ...p }));
    return (
        <Modal open onClose={onClose} title={term ? 'Edit term' : 'New term'}
            footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => save.mutate()} disabled={save.isPending}>Save</Button></>}>
            <div className="space-y-4">
                {error && <p className="text-[13px] text-rose-600">{error}</p>}
                <Field label="Name" error={errors.name}><input className={inputCls} value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
                <Field label="Slug (optional)" error={errors.slug}><input className={inputCls} value={form.slug ?? ''} onChange={(e) => set({ slug: e.target.value })} placeholder="auto from name" /></Field>
                <Field label="Parent term">
                    <select className={inputCls} value={form.parent_id ?? ''} onChange={(e) => set({ parent_id: e.target.value ? Number(e.target.value) : null })}>
                        <option value="">— None —</option>
                        {parents.filter((p) => p.id !== term?.id).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                </Field>
                <Field label="Description"><textarea rows={2} className={inputCls} value={form.description ?? ''} onChange={(e) => set({ description: e.target.value })} /></Field>
            </div>
        </Modal>
    );
}
