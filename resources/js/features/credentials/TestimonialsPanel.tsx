import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Loader2, Inbox, Printer, Trash2 } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { Modal, ConfirmDialog } from '@/components/Modal';
import { toApiError } from '@/lib/api';
import { listClassConfigs, listSetup } from '@/features/academic/api';
import { listStudents } from '@/features/students/api';
import { listTestimonials, issueTestimonial, deleteTestimonial, getTestimonial } from './api';
import { PrintableDocument } from './PrintableDocument';

const inputCls = 'w-full rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

export function TestimonialsPanel() {
    const qc = useQueryClient();
    const { data: rows = [], isLoading } = useQuery({ queryKey: ['testimonials'], queryFn: () => listTestimonials() });
    const [creating, setCreating] = useState(false);
    const [printing, setPrinting] = useState<number | null>(null);
    const [deleting, setDeleting] = useState<number | null>(null);

    const invalidate = () => qc.invalidateQueries({ queryKey: ['testimonials'] });
    const del = useMutation({ mutationFn: (id: number) => deleteTestimonial(id), onSuccess: () => { invalidate(); setDeleting(null); } });

    return (
        <Card>
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Testimonials</h3>
                    <p className="text-[12.5px] text-muted">{rows.length} issued</p>
                </div>
                <Button onClick={() => setCreating(true)}><Plus size={16} /> Issue testimonial</Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {isLoading ? (
                    <div className="flex items-center justify-center gap-2 py-16 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>
                ) : rows.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint"><Inbox size={22} /></div>
                        <p className="text-[14px] font-medium text-fg">No testimonials issued yet</p>
                    </div>
                ) : (
                    <table className="w-full min-w-[560px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">No.</th>
                                <th className="px-5 py-2.5 font-semibold">Student</th>
                                <th className="px-5 py-2.5 font-semibold">Issue date</th>
                                <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((r) => (
                                <tr key={r.id} className="border-b border-border last:border-0 hover:bg-surface-2/50">
                                    <td className="tnum px-5 py-3 font-medium text-fg">{r.certificate_number}</td>
                                    <td className="px-5 py-3 text-muted">{r.student?.name}</td>
                                    <td className="px-5 py-3 text-muted">{r.issue_date}</td>
                                    <td className="px-5 py-3">
                                        <div className="flex justify-end gap-1">
                                            <button onClick={() => setPrinting(r.id)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600" aria-label="Print"><Printer size={16} /></button>
                                            <button onClick={() => setDeleting(r.id)} className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500" aria-label="Delete"><Trash2 size={16} /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>

            {creating && <IssueForm onClose={() => setCreating(false)} onSaved={() => { invalidate(); setCreating(false); }} />}
            {printing !== null && <TestimonialPrintView id={printing} onClose={() => setPrinting(null)} />}
            <ConfirmDialog open={deleting !== null} onClose={() => setDeleting(null)} onConfirm={() => deleting !== null && del.mutate(deleting)} busy={del.isPending}
                title="Delete testimonial" message="Are you sure you want to delete this testimonial?" />
        </Card>
    );
}

function TestimonialPrintView({ id, onClose }: { id: number; onClose: () => void }) {
    const { data } = useQuery({ queryKey: ['testimonial', id], queryFn: () => getTestimonial(id) });
    if (!data) return null;
    const { certificate: c, branch, signatures } = data;

    return (
        <PrintableDocument title={`Testimonial — ${c.certificate_number}`} onClose={onClose}>
            <div className="text-center">
                <h1 className="text-2xl font-bold">{branch.name}</h1>
                {branch.address && <p className="text-sm">{branch.address}</p>}
                <h2 className="mt-6 text-lg font-semibold underline">Testimonial</h2>
                <p className="mt-1 text-sm">No: {c.certificate_number}</p>
            </div>
            <div className="mt-8 space-y-3 text-[15px] leading-relaxed">
                <p>This is to certify that <strong>{c.student.name}</strong> (Student ID: {c.student.student_uid}), son/daughter of <strong>{c.student.fathers_name}</strong>, was a student of this institution and bore a good moral character during his/her stay.</p>
                {c.character_certificate && <p>{c.character_certificate}</p>}
                {c.remarks && <p>Remarks: {c.remarks}</p>}
                <p>Date of issue: {c.issue_date}</p>
            </div>
            <div className="mt-16 flex justify-between">
                {signatures.slice(0, 2).map((s) => (
                    <div key={s.id} className="text-center text-sm">
                        <div className="mb-1 h-12 border-b border-slate-400" />
                        <p className="font-medium">{s.person_name}</p>
                        <p className="text-xs">{s.designation}</p>
                    </div>
                ))}
            </div>
        </PrintableDocument>
    );
}

function IssueForm({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
    const { data: studentsResponse } = useQuery({ queryKey: ['students-all'], queryFn: () => listStudents({ per_page: 500 }) });
    const students = studentsResponse?.data ?? [];
    const { data: years = [] } = useQuery({ queryKey: ['academic-years'], queryFn: () => listSetup('academic-years') });
    const { data: classConfigs = [] } = useQuery({ queryKey: ['academic-class-configs'], queryFn: listClassConfigs });

    const [form, setForm] = useState({
        student_id: '', issue_date: new Date().toISOString().slice(0, 10), character_certificate: '', remarks: '',
        academic_year_id: '', class_config_id: '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [error, setError] = useState<string | null>(null);

    const save = useMutation({
        mutationFn: () => issueTestimonial({
            ...form,
            student_id: Number(form.student_id),
            academic_year_id: Number(form.academic_year_id),
            class_config_id: Number(form.class_config_id),
        }),
        onSuccess: onSaved,
        onError: (e) => { const a = toApiError(e); setError(a.errors ? null : a.message); setErrors(a.errors ?? {}); },
    });

    const set = (k: string, v: string) => setForm((f) => ({ ...f, [k]: v }));

    return (
        <Modal
            open onClose={onClose} title="Issue testimonial"
            footer={<>
                <Button variant="outline" onClick={onClose} disabled={save.isPending}>Cancel</Button>
                <Button onClick={() => { setError(null); setErrors({}); save.mutate(); }} disabled={save.isPending}>
                    {save.isPending ? <Loader2 size={16} className="animate-spin" /> : null} Issue
                </Button>
            </>}
        >
            {error && <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700 dark:border-rose-500/25 dark:bg-rose-500/10 dark:text-rose-300">{error}</div>}
            <div className="space-y-4">
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Student <span className="text-rose-500">*</span></label>
                    <select value={form.student_id} onChange={(e) => set('student_id', e.target.value)} className={inputCls}>
                        <option value="">Select student</option>
                        {students.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.student_uid})</option>)}
                    </select>
                    {errors.student_id && <p className="mt-1.5 text-[12px] text-rose-500">{errors.student_id[0]}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Academic year <span className="text-rose-500">*</span></label>
                        <select value={form.academic_year_id} onChange={(e) => set('academic_year_id', e.target.value)} className={inputCls}>
                            <option value="">Select</option>
                            {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[13px] font-medium text-fg">Class <span className="text-rose-500">*</span></label>
                        <select value={form.class_config_id} onChange={(e) => set('class_config_id', e.target.value)} className={inputCls}>
                            <option value="">Select</option>
                            {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                        </select>
                    </div>
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Issue date <span className="text-rose-500">*</span></label>
                    <input type="date" value={form.issue_date} onChange={(e) => set('issue_date', e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Character certificate text</label>
                    <textarea rows={2} value={form.character_certificate} onChange={(e) => set('character_certificate', e.target.value)} className={inputCls} />
                </div>
                <div>
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Remarks</label>
                    <textarea rows={2} value={form.remarks} onChange={(e) => set('remarks', e.target.value)} className={inputCls} />
                </div>
            </div>
        </Modal>
    );
}
