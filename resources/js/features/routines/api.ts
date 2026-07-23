import { api } from '@/lib/api';

export interface Period {
    id: number;
    shift_id: number;
    shift_name?: string;
    name: string;
    name_bn: string | null;
    start_time: string;
    end_time: string;
    serial: number;
    status: boolean;
}

export interface RoutineSlot {
    id: number;
    class_config_id: number;
    period_id: number;
    period_name: string | null;
    day_of_week: number;
    subject_id: number;
    subject_name: string | null;
    employee_id: number | null;
    employee_name: string | null;
    class_label?: string | null;
    room: string | null;
    status: boolean;
}

export interface RoutineOption { id: number; name: string }
export interface RoutinePeriodOption { id: number; name: string; start_time: string; end_time: string }
export interface RoutineOptions {
    periods: RoutinePeriodOption[];
    subjects: RoutineOption[];
    employees: RoutineOption[];
}

const base = '/api/v1/routines';

export async function listPeriods(shiftId?: number): Promise<Period[]> {
    const { data } = await api.get(`${base}/periods`, { params: shiftId ? { shift_id: shiftId } : {} });
    return data.data as Period[];
}

export async function createPeriod(payload: Record<string, unknown>): Promise<Period> {
    const { data } = await api.post(`${base}/periods`, payload);
    return data.data as Period;
}

export async function updatePeriod(id: number, payload: Record<string, unknown>): Promise<Period> {
    const { data } = await api.put(`${base}/periods/${id}`, payload);
    return data.data as Period;
}

export async function deletePeriod(id: number): Promise<void> {
    await api.delete(`${base}/periods/${id}`);
}

export async function routineOptions(classConfigId: number): Promise<RoutineOptions> {
    const { data } = await api.get(`${base}/class-configs/${classConfigId}/options`);
    return data.data as RoutineOptions;
}

export async function classRoutine(classConfigId: number): Promise<RoutineSlot[]> {
    const { data } = await api.get(`${base}/class-configs/${classConfigId}`);
    return data.data as RoutineSlot[];
}

export async function teacherRoutine(employeeId: number): Promise<RoutineSlot[]> {
    const { data } = await api.get(`${base}/teachers/${employeeId}`);
    return data.data as RoutineSlot[];
}

export async function createRoutineSlot(payload: Record<string, unknown>): Promise<RoutineSlot> {
    const { data } = await api.post(`${base}`, payload);
    return data.data as RoutineSlot;
}

export async function updateRoutineSlot(id: number, payload: Record<string, unknown>): Promise<RoutineSlot> {
    const { data } = await api.put(`${base}/${id}`, payload);
    return data.data as RoutineSlot;
}

export async function deleteRoutineSlot(id: number): Promise<void> {
    await api.delete(`${base}/${id}`);
}

export const DAYS = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 6, label: 'Saturday' },
];
