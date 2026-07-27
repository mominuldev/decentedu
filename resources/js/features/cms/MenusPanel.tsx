import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, ChevronRight, ChevronUp, ChevronDown, CornerDownRight, Save } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import {
    listMenus, createMenu, deleteMenu, getMenu, saveMenuTree,
    inputCls, type MenuRow, type MenuTreeItem, type MenuDetail,
} from './api';
import { Field, Loading, Empty } from './PagesPanel';

export function MenusPanel() {
    const qc = useQueryClient();
    const { data: menus = [], isLoading } = useQuery({ queryKey: ['cms-menus'], queryFn: listMenus });
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<MenuRow | null>(null);

    const del = useMutation({ mutationFn: (id: number) => deleteMenu(id), onSuccess: () => { qc.invalidateQueries({ queryKey: ['cms-menus'] }); setDeleting(null); setSelectedId(null); } });

    return (
        <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
            <Card>
                <div className="flex items-center justify-between px-5 py-4">
                    <h3 className="text-[15px] font-semibold text-fg">Menus</h3>
                    <Button size="sm" onClick={() => setCreating(true)}><Plus size={15} /></Button>
                </div>
                <div className="border-t border-border">
                    {isLoading ? <Loading /> : menus.length === 0 ? <Empty label="No menus yet" /> : menus.map((m) => (
                        <div key={m.id} className={cn('group flex items-center border-b border-border last:border-0 hover:bg-surface-2/50', selectedId === m.id && 'bg-surface-2/60')}>
                            <button onClick={() => setSelectedId(m.id)} className="flex flex-1 items-center justify-between px-5 py-3 text-left">
                                <span className="text-[13.5px] font-medium text-fg">{m.name}</span>
                                <span className="flex items-center gap-2"><Badge size="sm" tone={m.is_active ? 'success' : 'neutral'}>{m.key}</Badge><ChevronRight size={15} className="text-faint" /></span>
                            </button>
                            <button onClick={() => setDeleting(m)} className="mr-2 rounded-lg p-1.5 text-faint opacity-0 hover:text-rose-500 group-hover:opacity-100"><Trash2 size={14} /></button>
                        </div>
                    ))}
                </div>
            </Card>

            {selectedId !== null ? <MenuTreeEditor menuId={selectedId} /> : <Card><Empty label="Select a menu to edit its items" /></Card>}

            {creating && <MenuForm onClose={() => setCreating(false)} onSaved={() => { qc.invalidateQueries({ queryKey: ['cms-menus'] }); setCreating(false); }} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && del.mutate(deleting.id)} busy={del.isPending} title="Delete menu" message={`Delete "${deleting?.name}"?`} />
        </div>
    );
}

function MenuForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
    const [form, setForm] = useState({ name: '', key: '', is_active: true });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);
    const save = useMutation({ mutationFn: () => createMenu(form), onSuccess: onSaved, onError: (e) => { const err = toApiError(e); setError(err.message); setErrors(err.errors ?? {}); } });
    return (
        <Modal open onClose={onClose} title="New menu"
            footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => save.mutate()} disabled={save.isPending}>Create</Button></>}>
            <div className="space-y-4">
                {error && <p className="text-[13px] text-rose-600">{error}</p>}
                <Field label="Name" error={errors.name}><input className={inputCls} value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></Field>
                <Field label="Key" error={errors.key}><input className={inputCls} value={form.key} onChange={(e) => setForm((f) => ({ ...f, key: e.target.value }))} placeholder="header" /></Field>
            </div>
        </Modal>
    );
}

let tmpId = -1;

function MenuTreeEditor({ menuId }: { menuId: number }) {
    const qc = useQueryClient();
    const { data, isLoading } = useQuery({ queryKey: ['cms-menu', menuId], queryFn: () => getMenu(menuId) });
    const [items, setItems] = useState<MenuTreeItem[]>([]);

    useEffect(() => { if (data) setItems(data.items); }, [data]);

    const save = useMutation({
        mutationFn: () => saveMenuTree(menuId, items),
        onSuccess: (tree) => { setItems(tree); qc.invalidateQueries({ queryKey: ['cms-menus'] }); },
    });

    if (isLoading || !data) return <Card><Loading /></Card>;

    const addRoot = () => setItems((it) => [...it, blankItem()]);

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">{data.menu.name}</h3>
                    <p className="text-[12.5px] text-muted">Items link to a page/post/term or a custom URL.</p>
                </div>
                <div className="flex gap-2">
                    <Button size="sm" variant="outline" onClick={addRoot}><Plus size={15} /> Item</Button>
                    <Button size="sm" onClick={() => save.mutate()} disabled={save.isPending}><Save size={15} /> {save.isPending ? 'Saving…' : 'Save'}</Button>
                </div>
            </div>
            <div className="space-y-2 border-t border-border p-4">
                {items.length === 0 ? <Empty label="No items — add one" /> :
                    <ItemList items={items} targets={data.link_targets} onChange={setItems} />}
            </div>
        </Card>
    );
}

function blankItem(): MenuTreeItem {
    return { id: tmpId--, label: 'New item', linkable_type: null, linkable_id: null, url: '', target: '_self', is_visible: true, children: [] };
}

function ItemList({ items, targets, onChange, depth = 0 }: { items: MenuTreeItem[]; targets: MenuDetail['link_targets']; onChange: (i: MenuTreeItem[]) => void; depth?: number }) {
    const update = (idx: number, next: MenuTreeItem) => onChange(items.map((x, i) => (i === idx ? next : x)));
    const remove = (idx: number) => onChange(items.filter((_, i) => i !== idx));
    const move = (idx: number, dir: -1 | 1) => {
        const j = idx + dir;
        if (j < 0 || j >= items.length) return;
        const next = [...items];
        [next[idx], next[j]] = [next[j], next[idx]];
        onChange(next);
    };
    const addChild = (idx: number) => update(idx, { ...items[idx], children: [...items[idx].children, blankItem()] });

    return (
        <div className={cn('space-y-2', depth > 0 && 'ml-5 border-l border-dashed border-border pl-3')}>
            {items.map((item, idx) => (
                <div key={item.id} className="rounded-xl border border-border bg-surface p-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <input value={item.label} onChange={(e) => update(idx, { ...item, label: e.target.value })}
                            className="flex-1 min-w-[120px] rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[13px] outline-none focus:border-brand-500" placeholder="Label" />
                        <LinkPicker item={item} targets={targets} onChange={(n) => update(idx, n)} />
                        <div className="flex items-center gap-0.5">
                            <button onClick={() => move(idx, -1)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2"><ChevronUp size={15} /></button>
                            <button onClick={() => move(idx, 1)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2"><ChevronDown size={15} /></button>
                            {depth === 0 && <button onClick={() => addChild(idx)} title="Add sub-item" className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"><CornerDownRight size={15} /></button>}
                            <button onClick={() => remove(idx)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"><Trash2 size={15} /></button>
                        </div>
                    </div>
                    {item.children.length > 0 && (
                        <div className="mt-2">
                            <ItemList items={item.children} targets={targets} depth={depth + 1} onChange={(c) => update(idx, { ...item, children: c })} />
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function LinkPicker({ item, targets, onChange }: { item: MenuTreeItem; targets: MenuDetail['link_targets']; onChange: (i: MenuTreeItem) => void }) {
    // Encoded value: "type:id" for content, "url" for custom.
    const value = item.linkable_type ? `${item.linkable_type}:${item.linkable_id}` : 'url';
    const apply = (v: string) => {
        if (v === 'url') { onChange({ ...item, linkable_type: null, linkable_id: null, url: item.url ?? '' }); return; }
        const [type, id] = v.split(':');
        onChange({ ...item, linkable_type: type, linkable_id: Number(id), url: null });
    };
    return (
        <div className="flex items-center gap-1.5">
            <select value={value} onChange={(e) => apply(e.target.value)}
                className="rounded-lg border border-border-strong bg-surface px-2 py-1.5 text-[12.5px] outline-none focus:border-brand-500">
                <option value="url">Custom URL</option>
                <optgroup label="Pages">{targets.pages.map((p) => <option key={`page:${p.id}`} value={`page:${p.id}`}>{p.title}</option>)}</optgroup>
                <optgroup label="Posts">{targets.posts.map((p) => <option key={`post:${p.id}`} value={`post:${p.id}`}>{p.title}</option>)}</optgroup>
                <optgroup label="Terms">{targets.terms.map((t) => <option key={`term:${t.id}`} value={`term:${t.id}`}>{t.name}</option>)}</optgroup>
            </select>
            {!item.linkable_type && (
                <input value={item.url ?? ''} onChange={(e) => onChange({ ...item, url: e.target.value })} placeholder="https://…"
                    className="w-40 rounded-lg border border-border-strong bg-surface px-2.5 py-1.5 text-[12.5px] outline-none focus:border-brand-500" />
            )}
        </div>
    );
}
