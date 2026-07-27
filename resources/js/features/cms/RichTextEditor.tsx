import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { useEffect } from 'react';
import { Bold, Italic, List, ListOrdered, Heading2, Link as LinkIcon, Undo, Redo } from 'lucide-react';
import { cn } from '@/lib/cn';

/**
 * Minimal Tiptap rich-text editor emitting HTML. Used for the post body and
 * the rich_text block. Controlled via `value`/`onChange`.
 */
export function RichTextEditor({ value, onChange }: { value: string; onChange: (html: string) => void }) {
    const editor = useEditor({
        extensions: [StarterKit, Link.configure({ openOnClick: false })],
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
        cn('grid h-8 w-8 place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-fg',
            active && 'bg-surface-2 text-brand-600');

    return (
        <div className="overflow-hidden rounded-xl border border-border-strong bg-surface">
            <div className="flex flex-wrap items-center gap-0.5 border-b border-border px-1.5 py-1">
                <button type="button" className={btn(editor.isActive('bold'))} onClick={() => editor.chain().focus().toggleBold().run()}><Bold size={15} /></button>
                <button type="button" className={btn(editor.isActive('italic'))} onClick={() => editor.chain().focus().toggleItalic().run()}><Italic size={15} /></button>
                <button type="button" className={btn(editor.isActive('heading', { level: 2 }))} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}><Heading2 size={15} /></button>
                <button type="button" className={btn(editor.isActive('bulletList'))} onClick={() => editor.chain().focus().toggleBulletList().run()}><List size={15} /></button>
                <button type="button" className={btn(editor.isActive('orderedList'))} onClick={() => editor.chain().focus().toggleOrderedList().run()}><ListOrdered size={15} /></button>
                <button type="button" className={btn(editor.isActive('link'))} onClick={() => {
                    const url = window.prompt('Link URL');
                    if (url) editor.chain().focus().setLink({ href: url }).run();
                    else editor.chain().focus().unsetLink().run();
                }}><LinkIcon size={15} /></button>
                <div className="mx-1 h-5 w-px bg-border" />
                <button type="button" className={btn(false)} onClick={() => editor.chain().focus().undo().run()}><Undo size={15} /></button>
                <button type="button" className={btn(false)} onClick={() => editor.chain().focus().redo().run()}><Redo size={15} /></button>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
