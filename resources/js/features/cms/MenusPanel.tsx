import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Trash2, Loader2, Inbox } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listMenus, createMenu, deleteMenu, createMenuItem, deleteMenuItem, type MenuRow } from './api';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function MenusPanel() {
    const qc = useQueryClient();
    const { data: menus = [], isLoading } = useQuery({ queryKey: ['cms-menus'], queryFn: listMenus });
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<MenuRow | null>(null);
    const [addingItemTo, setAddingItemTo] = useState<MenuRow | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['cms-menus'] });
    const delMenu = useMutation({ mutationFn: (id: number) => deleteMenu(id), onSuccess: () => { invalidate(); setDeleting(null); } });
    const delItem = useMutation({ mutationFn: (id: number) => deleteMenuItem(id), onSuccess: invalidate });

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Menus</h3>
                    <p className="text-[12.5px] text-muted">Header/footer navigation for the public site.</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Add menu</Button>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
            ) : menus.length === 0 ? (
                <Card><div className="flex flex-col items-center justify-center gap-2 py-16 text-center"><div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div><p className="text-[14px] font-medium text-fg">No menus yet</p></div></Card>
            ) : (
                menus.map((menu) => (
                    <Card key={menu.id}>
                        <div className="flex items-center justify-between px-5 py-4">
                            <div className="flex items-center gap-2">
                                <h4 className="text-[14px] font-semibold text-fg">{menu.name}</h4>
                                <Badge tone="brand">{menu.location}</Badge>
                                <Badge tone={menu.status ? 'success' : 'neutral'}>{menu.status ? 'Active' : 'Inactive'}</Badge>
                            </div>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" onClick={() => setAddingItemTo(menu)}><Plus size={14} /> Add item</Button>
                                <button onClick={() => setDeleting(menu)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                            </div>
                        </div>
                        <div className="border-t border-border px-5 py-3">
                            {(menu.items ?? []).length === 0 ? (
                                <p className="text-[13px] text-faint">No items yet.</p>
                            ) : (
                                <ul className="space-y-1.5">
                                    {(menu.items ?? []).map((item) => (
                                        <li key={item.id} className="flex items-center justify-between rounded-lg px-2 py-1.5 text-[13.5px] hover:bg-surface-2">
                                            <span className="text-fg">{item.label} <span className="text-faint">{item.url}</span></span>
                                            <button onClick={() => delItem.mutate(item.id)} className="rounded-lg p-1 text-faint hover:text-rose-500" aria-label="Delete item"><Trash2 size={14} /></button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </Card>
                ))
            )}

            {creating && <MenuForm onClose={() => setCreating(false)} onSaved={() => { invalidate(); setCreating(false); }} />}
            {addingItemTo && <MenuItemForm menu={addingItemTo} onClose={() => setAddingItemTo(null)} onSaved={() => { invalidate(); setAddingItemTo(null); }} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => deleting && delMenu.mutate(deleting.id)} busy={delMenu.isPending}
                title="Delete menu" message={`Delete "${deleting?.name}" and all its items?`} />
        </div>
    );
}

function MenuForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
    const [name, setName] = useState('');
    const [location, setLocation] = useState<'header' | 'footer'>('header');
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => createMenu({ name, location, status: true }),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    return (
        <Modal open onClose={onClose} title="Add menu" footer={<>
            <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
            <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !name}>
                {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Create
            </Button>
        </>}>
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Name <span className="text-rose-500">*</span></label>
                    <input value={name} onChange={(e) => setName(e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Location</label>
                    <select value={location} onChange={(e) => setLocation(e.target.value as 'header' | 'footer')} className={inputCls}>
                        <option value="header">Header</option>
                        <option value="footer">Footer</option>
                    </select>
                </div>
            </div>
        </Modal>
    );
}

function MenuItemForm({ menu, onClose, onSaved }: { menu: MenuRow; onClose: () => void; onSaved: () => void }) {
    const [label, setLabel] = useState('');
    const [url, setUrl] = useState('');
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => createMenuItem({ menu_id: menu.id, label, url: url || null, serial: (menu.items?.length ?? 0) }),
        onSuccess: onSaved,
        onError: (e) => setError(toApiError(e).message),
    });

    return (
        <Modal open onClose={onClose} title={`Add item to "${menu.name}"`} footer={<>
            <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
            <Button onClick={() => { setError(null); save.mutate(); }} disabled={save.isPending || !label}>
                {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Add
            </Button>
        </>}>
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Label <span className="text-rose-500">*</span></label>
                    <input value={label} onChange={(e) => setLabel(e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">URL</label>
                    <input value={url} onChange={(e) => setUrl(e.target.value)} placeholder="/notices" className={inputCls} />
                </div>
            </div>
        </Modal>
    );
}
