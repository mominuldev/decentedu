import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { TextAlign } from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import { useEffect } from 'react';
import {
    Bold, Italic, List, ListOrdered, Link as LinkIcon, Undo, Redo,
    AlignLeft, AlignCenter, AlignRight, AlignJustify, Palette,
} from 'lucide-react';
import { cn } from '@/lib/cn';

const COLOR_PRESETS = [
    { label: 'Default', value: '' },
    { label: 'Brand Blue', value: '#2563eb' },
    { label: 'Emerald Green', value: '#16a34a' },
    { label: 'Rose Red', value: '#e11d48' },
    { label: 'Amber Gold', value: '#d97706' },
    { label: 'Purple', value: '#9333ea' },
    { label: 'Muted Slate', value: '#64748b' },
    { label: 'Dark Slate', value: '#0f172a' },
];

/**
 * Tiptap rich-text editor emitting HTML. Used globally across CMS forms
 * (posts, pages, notices, events, galleries, blocks). Controlled via `value`/`onChange`.
 * Supports H1-H6 headings, text alignment, and custom text colors globally.
 */
export function RichTextEditor({ value, onChange }: { value: string; onChange: (html: string) => void }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3, 4, 5, 6],
                },
            }),
            Link.configure({ openOnClick: false }),
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
            TextStyle,
            Color,
        ],
        content: value || '',
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
        editorProps: {
            attributes: {
                class: 'prose prose-sm dark:prose-invert max-w-none min-h-[160px] px-3.5 py-3 focus:outline-none text-fg',
            },
        },
    });

    // Keep external resets (e.g. loading a record) in sync without clobbering typing.
    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value || '', { emitUpdate: false });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, editor]);

    if (!editor) return null;

    const btn = (active: boolean) =>
        cn('grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-fg transition-colors',
            active && 'bg-surface-2 text-brand-600 font-bold');

    const currentHeading = editor.isActive('heading', { level: 1 }) ? 'h1'
        : editor.isActive('heading', { level: 2 }) ? 'h2'
        : editor.isActive('heading', { level: 3 }) ? 'h3'
        : editor.isActive('heading', { level: 4 }) ? 'h4'
        : editor.isActive('heading', { level: 5 }) ? 'h5'
        : editor.isActive('heading', { level: 6 }) ? 'h6'
        : 'p';

    const currentColor = editor.getAttributes('textStyle').color ?? '';

    return (
        <div className="overflow-hidden rounded-xl border border-border-strong bg-surface">
            <div className="flex flex-wrap items-center gap-1 border-b border-border px-2 py-1.5 bg-surface-2/40">
                {/* Heading Dropdown (H1-H6) */}
                <select
                    value={currentHeading}
                    onChange={(e) => {
                        const val = e.target.value;
                        if (val === 'p') {
                            editor.chain().focus().setParagraph().run();
                        } else if (val.startsWith('h')) {
                            const level = Number(val.replace('h', '')) as 1 | 2 | 3 | 4 | 5 | 6;
                            editor.chain().focus().toggleHeading({ level }).run();
                        }
                    }}
                    className="h-8 rounded-lg border border-border-strong bg-surface px-2 text-[12px] font-medium text-fg outline-none focus:border-brand-500"
                    aria-label="Heading style"
                >
                    <option value="p">Paragraph</option>
                    <option value="h1">Heading 1 (H1)</option>
                    <option value="h2">Heading 2 (H2)</option>
                    <option value="h3">Heading 3 (H3)</option>
                    <option value="h4">Heading 4 (H4)</option>
                    <option value="h5">Heading 5 (H5)</option>
                    <option value="h6">Heading 6 (H6)</option>
                </select>

                <div className="mx-0.5 h-5 w-px bg-border-strong" />

                {/* Text Formatting */}
                <button type="button" className={btn(editor.isActive('bold'))} onClick={() => editor.chain().focus().toggleBold().run()} title="Bold"><Bold size={15} /></button>
                <button type="button" className={btn(editor.isActive('italic'))} onClick={() => editor.chain().focus().toggleItalic().run()} title="Italic"><Italic size={15} /></button>

                <div className="mx-0.5 h-5 w-px bg-border-strong" />

                {/* Text Alignment */}
                <button type="button" className={btn(editor.isActive({ textAlign: 'left' }))} onClick={() => editor.chain().focus().setTextAlign('left').run()} title="Align Left"><AlignLeft size={15} /></button>
                <button type="button" className={btn(editor.isActive({ textAlign: 'center' }))} onClick={() => editor.chain().focus().setTextAlign('center').run()} title="Align Center"><AlignCenter size={15} /></button>
                <button type="button" className={btn(editor.isActive({ textAlign: 'right' }))} onClick={() => editor.chain().focus().setTextAlign('right').run()} title="Align Right"><AlignRight size={15} /></button>
                <button type="button" className={btn(editor.isActive({ textAlign: 'justify' }))} onClick={() => editor.chain().focus().setTextAlign('justify').run()} title="Justify"><AlignJustify size={15} /></button>

                <div className="mx-0.5 h-5 w-px bg-border-strong" />

                {/* Text Color Picker & Presets */}
                <div className="flex items-center gap-1.5">
                    <label className="relative grid h-8 w-8 cursor-pointer place-items-center rounded-lg hover:bg-surface-2" title="Pick Text Color">
                        <Palette size={15} style={{ color: currentColor || undefined }} />
                        <input
                            type="color"
                            value={currentColor || '#000000'}
                            onChange={(e) => editor.chain().focus().setColor(e.target.value).run()}
                            className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                        />
                    </label>
                    <select
                        value={currentColor}
                        onChange={(e) => {
                            const val = e.target.value;
                            if (val) {
                                editor.chain().focus().setColor(val).run();
                            } else {
                                editor.chain().focus().unsetColor().run();
                            }
                        }}
                        className="h-8 rounded-lg border border-border-strong bg-surface px-1.5 text-[11.5px] font-medium text-fg outline-none focus:border-brand-500"
                        aria-label="Color presets"
                    >
                        {COLOR_PRESETS.map((p) => (
                            <option key={p.value} value={p.value}>{p.label}</option>
                        ))}
                    </select>
                </div>

                <div className="mx-0.5 h-5 w-px bg-border-strong" />

                {/* Lists & Links */}
                <button type="button" className={btn(editor.isActive('bulletList'))} onClick={() => editor.chain().focus().toggleBulletList().run()} title="Bullet List"><List size={15} /></button>
                <button type="button" className={btn(editor.isActive('orderedList'))} onClick={() => editor.chain().focus().toggleOrderedList().run()} title="Numbered List"><ListOrdered size={15} /></button>
                <button type="button" className={btn(editor.isActive('link'))} onClick={() => {
                    const url = window.prompt('Link URL');
                    if (url) editor.chain().focus().setLink({ href: url }).run();
                    else editor.chain().focus().unsetLink().run();
                }} title="Insert Link"><LinkIcon size={15} /></button>

                <div className="mx-0.5 h-5 w-px bg-border-strong" />

                {/* Undo / Redo */}
                <button type="button" className={btn(false)} onClick={() => editor.chain().focus().undo().run()} title="Undo"><Undo size={15} /></button>
                <button type="button" className={btn(false)} onClick={() => editor.chain().focus().redo().run()} title="Redo"><Redo size={15} /></button>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
