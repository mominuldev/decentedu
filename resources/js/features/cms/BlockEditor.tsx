import { useState } from 'react';
import {
    DndContext, closestCenter, PointerSensor, useSensor, useSensors, type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext, verticalListSortingStrategy, arrayMove, useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
    GripVertical, ChevronDown, ChevronRight, Eye, EyeOff, Trash2, Plus, Copy,
    LayoutTemplate, Type, Image as ImageIcon, Images, Video, MousePointerClick,
    HelpCircle, Code2, Rows3, PanelTop, ShoppingBag, Square, Info, Milestone,
    Heading, Quote, GraduationCap, BellRing, LayoutGrid, Newspaper, SeparatorHorizontal, Megaphone, type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/cn';
import { ConfirmDialog } from '@/components/Modal';
import { BlockFields } from './BlockForms';
import type { AssetPayload, BlockTypeOption, EditorBlock } from './api';

let uid = -1;
const nextKey = () => uid--;

/** A block plus a stable client key (real id or a negative temp id). */
type Keyed = EditorBlock & { _key: number };

function withKeys(blocks: EditorBlock[]): Keyed[] {
    return blocks.map((b) => ({ ...b, _key: b._key ?? b.id ?? nextKey() }));
}

/* Icon shown in the palette + as the block's glyph. */
const BLOCK_ICONS: Record<string, LucideIcon> = {
    hero: LayoutTemplate,
    heading: Heading,
    page_header: PanelTop,
    rich_text: Type,
    image: ImageIcon,
    gallery: Images,
    video_embed: Video,
    cta: MousePointerClick,
    faq: HelpCircle,
    posts_list: Newspaper,
    html: Code2,
    divider: SeparatorHorizontal,
    section: Rows3,
    card_list: LayoutGrid,
    notice_board: BellRing,
    quote: Quote,
    teachers: GraduationCap,
    about: Info,
    milestones_timeline: Milestone,
    announcement_strip: Megaphone,
    product_grid: ShoppingBag,
};
const iconFor = (type: string): LucideIcon => BLOCK_ICONS[type] ?? Square;

const stripHtml = (s: string) => s.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

/** One-line summary of a block's contents, shown next to its label. */
function blockSummary(b: EditorBlock): string {
    const p = b.payload ?? {};
    switch (b.type) {
        case 'hero': return (p.heading as string) || '';
        case 'heading': return (p.title as string) || (p.heading as string) || (p.subtitle as string) || '';
        case 'announcement_strip': return (p.title as string) || (p.heading as string) || 'Notice Strip';
        case 'page_header': return (p.heading as string) || '';
        case 'rich_text': return stripHtml((p.content as string) ?? '');
        case 'html': return 'Custom HTML';
        case 'image': return (p.caption as string) || ((p.asset_id_preview as AssetPayload)?.name ?? '');
        case 'gallery': return `${((p.asset_ids as unknown[]) ?? []).length} images`;
        case 'video_embed': return (p.title as string) || (p.url as string) || '';
        case 'cta': return (p.title as string) || (p.heading as string) || '';
        case 'about': return (p.title as string) || (p.heading as string) || '';
        case 'milestones_timeline': return `${((p.items as unknown[]) ?? []).length} milestones`;
        case 'card_list': return `${((p.items as unknown[]) ?? []).length} cards`;
        case 'notice_board': return (p.heading as string) || (p.title as string) || '';
        case 'quote': return (p.name as string) || (p.quote_message as string) || '';
        case 'teachers': return (p.title as string) || (p.heading as string) || '';
        case 'faq': return `${((p.items as unknown[]) ?? []).length} questions`;
        case 'posts_list': return `${(p.mode as string) ?? 'latest'} · ${(p.limit as number) ?? 3}`;
        case 'divider': return (p.style as string) ?? 'line';
        case 'section': return `${((p.blocks as unknown[]) ?? []).length} blocks`;
        default: return '';
    }
}

export function BlockEditor({
    blocks,
    blockTypes,
    onChange,
    depth = 0,
}: {
    blocks: EditorBlock[];
    blockTypes: BlockTypeOption[];
    onChange: (blocks: EditorBlock[]) => void;
    depth?: number;
}) {
    // Keys are derived per render from the incoming ids; temp keys are stable
    // for the component's lifetime because parent state holds the same objects.
    const items = withKeys(blocks);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));
    const [adding, setAdding] = useState(false);
    const [deletingKey, setDeletingKey] = useState<number | null>(null);

    const deletingBlock = items.find((b) => b._key === deletingKey);
    const deletingBlockLabel = deletingBlock
        ? (blockTypes.find((t) => t.type === deletingBlock.type)?.label ?? deletingBlock.type)
        : 'block';

    const emit = (next: Keyed[]) => onChange(next);

    const onDragEnd = (e: DragEndEvent) => {
        const { active, over } = e;
        if (!over || active.id === over.id) return;
        const oldIndex = items.findIndex((b) => b._key === active.id);
        const newIndex = items.findIndex((b) => b._key === over.id);
        emit(arrayMove(items, oldIndex, newIndex));
    };

    const addBlock = (type: string) => {
        emit([...items, { id: null, type, is_visible: true, payload: {}, _key: nextKey() }]);
        setAdding(false);
    };

    const duplicateBlock = (key: number) => {
        const index = items.findIndex((b) => b._key === key);
        if (index === -1) return;
        const target = items[index];
        const clonedPayload = JSON.parse(JSON.stringify(target.payload ?? {}));
        const newBlock: Keyed = {
            id: null,
            type: target.type,
            is_visible: target.is_visible,
            payload: clonedPayload,
            _key: nextKey(),
        };
        const next = [...items];
        next.splice(index + 1, 0, newBlock);
        emit(next);
    };

    const patch = (key: number, next: Partial<EditorBlock>) =>
        emit(items.map((b) => (b._key === key ? { ...b, ...next } : b)));

    const list = (
        <div className={cn('space-y-2', depth > 0 && 'rounded-xl border border-dashed border-border p-2')}>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={items.map((b) => b._key)} strategy={verticalListSortingStrategy}>
                    {items.map((b) => (
                        <BlockCard
                            key={b._key}
                            block={b}
                            blockTypes={blockTypes}
                            depth={depth}
                            onChangePayload={(payload) => patch(b._key, { payload })}
                            onToggle={() => patch(b._key, { is_visible: !b.is_visible })}
                            onDuplicate={() => duplicateBlock(b._key)}
                            onDelete={() => setDeletingKey(b._key)}
                        />
                    ))}
                </SortableContext>
            </DndContext>

            {items.length === 0 && (
                <p className="rounded-xl border border-dashed border-border py-8 text-center text-[13px] text-muted">
                    No blocks yet.{depth === 0 ? ' Pick one from All Blocks →' : ''}
                </p>
            )}

            {/* Nested lists keep an inline picker; the top level uses the side palette. */}
            {depth > 0 && (
                <div className="relative">
                    <button type="button" onClick={() => setAdding((v) => !v)}
                        className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border-strong py-2 text-[13px] font-medium text-muted hover:border-brand-400 hover:text-brand-600">
                        <Plus size={15} /> Add block
                    </button>
                    {adding && (
                        <div className="absolute z-10 mt-1 grid w-full grid-cols-2 gap-1 rounded-xl border border-border bg-surface p-2 shadow-[var(--shadow-pop)]">
                            {blockTypes.map((t) => (
                                <button key={t.type} type="button" onClick={() => addBlock(t.type)}
                                    className="rounded-lg px-2.5 py-2 text-left text-[13px] text-fg hover:bg-surface-2">{t.label}</button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <ConfirmDialog
                open={deletingKey !== null}
                onClose={() => setDeletingKey(null)}
                onConfirm={() => {
                    if (deletingKey !== null) {
                        emit(items.filter((x) => x._key !== deletingKey));
                        setDeletingKey(null);
                    }
                }}
                title="Delete block"
                message={`Are you sure you want to delete this "${deletingBlockLabel}" block? This action cannot be undone.`}
            />
        </div>
    );

    if (depth > 0) return list;

    return (
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
            <div className="min-w-0 flex-1">{list}</div>
            <BlockPalette blockTypes={blockTypes} onAdd={addBlock} />
        </div>
    );
}

function BlockPalette({ blockTypes, onAdd }: { blockTypes: BlockTypeOption[]; onAdd: (type: string) => void }) {
    return (
        <div className="w-full shrink-0 overflow-hidden rounded-2xl border border-border lg:sticky lg:top-4 lg:w-72">
            <div className="bg-brand-500 py-3 text-center text-[14px] font-semibold text-white">All Blocks</div>
            <div className="grid grid-cols-3 gap-2 p-3">
                {blockTypes.map((t) => {
                    const Icon = iconFor(t.type);
                    return (
                        <button key={t.type} type="button" onClick={() => onAdd(t.type)}
                            className="flex flex-col items-center justify-start gap-2 rounded-xl border border-dashed border-border p-3 text-center transition-colors hover:border-brand-400 hover:bg-surface-2">
                            <span className="grid h-10 w-10 place-items-center rounded-lg bg-surface-2 text-muted">
                                <Icon size={18} />
                            </span>
                            <span className="text-[12px] font-medium leading-tight text-fg">{t.label}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function BlockCard({
    block, blockTypes, depth, onChangePayload, onToggle, onDuplicate, onDelete,
}: {
    block: Keyed;
    blockTypes: BlockTypeOption[];
    depth: number;
    onChangePayload: (p: Record<string, unknown>) => void;
    onToggle: () => void;
    onDuplicate: () => void;
    onDelete: () => void;
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: block._key });
    const [open, setOpen] = useState(block.id === null);
    const label = blockTypes.find((t) => t.type === block.type)?.label ?? block.type;
    const summary = blockSummary(block);

    return (
        <div ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('rounded-2xl border border-border bg-surface-2/40', isDragging && 'opacity-60 shadow-[var(--shadow-pop)]')}>
            <div className="flex items-center gap-3 px-3.5 py-3">
                <button type="button" className="cursor-grab text-faint hover:text-fg" {...attributes} {...listeners}><GripVertical size={16} /></button>
                <button type="button" onClick={() => setOpen((v) => !v)} className="flex min-w-0 flex-1 items-center gap-2 text-left">
                    {open ? <ChevronDown size={15} className="shrink-0 text-faint" /> : <ChevronRight size={15} className="shrink-0 text-faint" />}
                    <span className={cn('shrink-0 text-[14px] font-semibold', block.is_visible ? 'text-fg' : 'text-faint line-through')}>{label}</span>
                    {summary && <span className="truncate text-[13px] text-muted">{summary}</span>}
                </button>
                <button type="button" onClick={onToggle} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-fg" title={block.is_visible ? 'Hide' : 'Show'}>
                    {block.is_visible ? <Eye size={16} /> : <EyeOff size={16} />}
                </button>
                <button type="button" onClick={onDuplicate} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" title="Duplicate block" aria-label="Duplicate block">
                    <Copy size={16} />
                </button>
                <button type="button" onClick={onDelete} className="rounded-lg p-1.5 text-rose-500 hover:bg-rose-500/10 hover:text-rose-600" title="Delete"><Trash2 size={16} /></button>
            </div>
            {open && (
                <div className="border-t border-border px-3.5 py-3">
                    <BlockFields type={block.type} payload={block.payload} onChange={onChangePayload} blockTypes={blockTypes} depth={depth} />
                </div>
            )}
        </div>
    );
}
