import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { Card, Button } from '@/components/ui';
import { toApiError, api } from '@/lib/api';
import { listClassConfigs } from '@/features/academic/api';
import { listSetup } from './api';

interface AdmitCardData {
    students: { student_id: number; roll: string; name: string }[];
    routine: { subject_name: string; exam_date: string; start_time: string; end_time: string; room_no: string | null }[];
}
interface SeatRow { student_id: number; roll: string; name: string; room: string; seat_no: number }
interface AttendanceRow { student_id: number; roll: string; name: string }

export function AdmitPanel() {
    const { data: classConfigs = [] } = useQuery({ queryKey: ['class-configs'], queryFn: listClassConfigs });
    const { data: exams = [] } = useQuery({ queryKey: ['exam-setup', 'exams'], queryFn: () => listSetup('exams') });

    const [classConfigId, setClassConfigId] = useState(0);
    const [examId, setExamId] = useState(0);
    const [view, setView] = useState<'admit' | 'seat' | 'attendance'>('admit');
    const [roomsText, setRoomsText] = useState('Room 1:30\nRoom 2:30');

    const ready = !!classConfigId && !!examId;

    const admitCard = useMutation({
        mutationFn: async () => (await api.get('/api/v1/examinations/admit/card', { params: { class_config_id: classConfigId, exam_id: examId } })).data.data as AdmitCardData,
    });
    const attendanceSheet = useMutation({
        mutationFn: async () => (await api.get('/api/v1/examinations/admit/attendance-sheet', { params: { class_config_id: classConfigId } })).data.data as AttendanceRow[],
    });
    const seatPlan = useMutation({
        mutationFn: async () => {
            const rooms = roomsText.split('\n').filter(Boolean).map((line) => {
                const [name, capacity] = line.split(':');
                return { name: name.trim(), capacity: Number(capacity) || 30 };
            });

            return (await api.post('/api/v1/examinations/admit/seat-plan', { class_config_id: classConfigId, rooms })).data.data as SeatRow[];
        },
    });

    const selectCls = 'rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25';

    const run = () => {
        if (view === 'admit') admitCard.mutate();
        else if (view === 'seat') seatPlan.mutate();
        else attendanceSheet.mutate();
    };

    return (
        <Card>
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <h3 className="text-[15px] font-semibold text-fg">Admit card, seat plan &amp; attendance sheet</h3>
                    <p className="text-[12.5px] text-muted">Printable exam-day documents for one class × exam</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <select value={classConfigId} onChange={(e) => setClassConfigId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Class</option>
                        {classConfigs.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                    </select>
                    <select value={examId} onChange={(e) => setExamId(Number(e.target.value))} className={selectCls}>
                        <option value={0} disabled>Exam</option>
                        {exams.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <select value={view} onChange={(e) => setView(e.target.value as typeof view)} className={selectCls}>
                        <option value="admit">Admit card</option>
                        <option value="seat">Seat plan</option>
                        <option value="attendance">Attendance sheet</option>
                    </select>
                </div>
            </div>

            {view === 'seat' && (
                <div className="border-t border-border px-5 py-4">
                    <label className="mb-1.5 block text-[13px] font-medium text-fg">Rooms (one per line, "Name:Capacity")</label>
                    <textarea rows={3} value={roomsText} onChange={(e) => setRoomsText(e.target.value)}
                        className="w-full max-w-sm rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[13.5px] text-fg outline-none focus:border-brand-500" />
                </div>
            )}

            <div className="flex justify-end border-t border-border px-5 py-4">
                <Button onClick={run} disabled={!ready || admitCard.isPending || seatPlan.isPending || attendanceSheet.isPending}>
                    {(admitCard.isPending || seatPlan.isPending || attendanceSheet.isPending) && <Loader2 size={16} className="animate-spin" />}
                    Generate
                </Button>
            </div>

            <div className="overflow-x-auto border-t border-border">
                {view === 'admit' && admitCard.data && (
                    <div className="p-5 text-[13.5px]">
                        <p className="mb-2 font-semibold text-fg">Exam schedule</p>
                        <ul className="mb-4 space-y-1 text-muted">
                            {admitCard.data.routine.map((r, i) => (
                                <li key={i}>{r.subject_name} — {r.exam_date} {r.start_time}–{r.end_time} {r.room_no ? `(${r.room_no})` : ''}</li>
                            ))}
                        </ul>
                        <p className="mb-2 font-semibold text-fg">{admitCard.data.students.length} students</p>
                    </div>
                )}
                {view === 'seat' && seatPlan.data && (
                    <table className="w-full min-w-[420px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Roll</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Room</th>
                                <th className="px-5 py-2.5 font-semibold">Seat</th>
                            </tr>
                        </thead>
                        <tbody>
                            {seatPlan.data.map((s) => (
                                <tr key={s.student_id} className="border-b border-border last:border-0">
                                    <td className="tnum px-5 py-2 text-muted">{s.roll}</td>
                                    <td className="px-5 py-2 font-medium text-fg">{s.name}</td>
                                    <td className="px-5 py-2 text-muted">{s.room}</td>
                                    <td className="tnum px-5 py-2 text-muted">{s.seat_no}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {view === 'attendance' && attendanceSheet.data && (
                    <table className="w-full min-w-[420px] text-left text-[13.5px]">
                        <thead>
                            <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                                <th className="px-5 py-2.5 font-semibold">Roll</th>
                                <th className="px-5 py-2.5 font-semibold">Name</th>
                                <th className="px-5 py-2.5 font-semibold">Signature</th>
                            </tr>
                        </thead>
                        <tbody>
                            {attendanceSheet.data.map((s) => (
                                <tr key={s.student_id} className="border-b border-border last:border-0">
                                    <td className="tnum px-5 py-2 text-muted">{s.roll}</td>
                                    <td className="px-5 py-2 font-medium text-fg">{s.name}</td>
                                    <td className="px-5 py-2 text-faint">&nbsp;</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
                {(admitCard.isError || seatPlan.isError || attendanceSheet.isError) && (
                    <div className="p-5 text-[13.5px] text-rose-500">
                        {toApiError(admitCard.error ?? seatPlan.error ?? attendanceSheet.error).message}
                    </div>
                )}
            </div>
        </Card>
    );
}
