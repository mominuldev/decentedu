import { useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Printer, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui';
import { useAuth } from '@/features/auth/AuthProvider';
import { listStudents } from './api';

/**
 * Standalone routed print view (same pattern as PrintClassRoutinePage) — no server PDF, just
 * print CSS (.print-area, resources/css/app.css) + window.print(). Filters arrive via the query
 * string so this page can be opened in a new tab from the Students list's current filter state.
 */
export default function PrintStudentListPage() {
    const [searchParams] = useSearchParams();
    const { session } = useAuth();

    const filters = {
        search: searchParams.get('search') || undefined,
        status: searchParams.get('status') || undefined,
        class_config_id: searchParams.get('class_config_id') ? Number(searchParams.get('class_config_id')) : undefined,
        academic_year_id: searchParams.get('academic_year_id') ? Number(searchParams.get('academic_year_id')) : undefined,
        class_id: searchParams.get('class_id') ? Number(searchParams.get('class_id')) : undefined,
        section_id: searchParams.get('section_id') ? Number(searchParams.get('section_id')) : undefined,
        roll: searchParams.get('roll') || undefined,
    };

    const { data: response, isLoading } = useQuery({
        queryKey: ['print-students', filters],
        queryFn: () => listStudents({ ...filters, per_page: 200 }),
    });

    const students = response?.data ?? [];

    if (isLoading) {
        return <div className="flex min-h-screen items-center justify-center gap-2 text-muted"><Loader2 size={18} className="animate-spin" /> Loading…</div>;
    }

    return (
        <div className="min-h-screen bg-bg">
            <div className="flex items-center justify-between border-b border-border px-6 py-4 print:hidden">
                <h1 className="text-[16px] font-semibold text-fg">Student List</h1>
                <Button onClick={() => window.print()}><Printer size={16} /> Print</Button>
            </div>

            <div className="print-area mx-auto max-w-4xl bg-white p-8 text-slate-900">
                <div className="mb-6 text-center">
                    <h2 className="text-xl font-bold">{session?.active_branch?.name}</h2>
                    <p className="text-sm text-slate-600">Student List — {students.length} student{students.length === 1 ? '' : 's'}</p>
                </div>
                <table className="w-full table-fixed border-collapse text-left text-[12px]">
                    <thead>
                        <tr>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">UID</th>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Name</th>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Class</th>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Roll</th>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Father's Name</th>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Mobile</th>
                            <th className="border border-slate-300 px-2 py-2 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {students.map((s) => (
                            <tr key={s.id}>
                                <td className="border border-slate-300 px-2 py-1.5">{s.student_uid}</td>
                                <td className="border border-slate-300 px-2 py-1.5">{s.name}</td>
                                <td className="border border-slate-300 px-2 py-1.5">{s.current_enrollment?.class_config?.name ?? '—'}</td>
                                <td className="border border-slate-300 px-2 py-1.5">{s.current_enrollment?.roll ?? '—'}</td>
                                <td className="border border-slate-300 px-2 py-1.5">{s.fathers_name}</td>
                                <td className="border border-slate-300 px-2 py-1.5">{s.mobile ?? '—'}</td>
                                <td className="border border-slate-300 px-2 py-1.5">{s.status}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
