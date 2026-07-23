import { api } from '@/lib/api';

export interface SetupRow {
    id: number;
    name: string;
    name_bn?: string | null;
    fee_head_id?: number;
    fee_head_name?: string;
    type?: string;
    value?: string;
    serial: number;
    status: boolean;
    [key: string]: unknown;
}

const base = '/api/v1/fees';

export async function listSetup(resource: string, params: Record<string, unknown> = {}): Promise<SetupRow[]> {
    const { data } = await api.get(`${base}/${resource}`, { params: { per_page: 200, ...params } });
    return data.data as SetupRow[];
}
export async function createSetup(resource: string, payload: Record<string, unknown>): Promise<SetupRow> {
    const { data } = await api.post(`${base}/${resource}`, payload);
    return data.data as SetupRow;
}
export async function updateSetup(resource: string, id: number, payload: Record<string, unknown>): Promise<SetupRow> {
    const { data } = await api.put(`${base}/${resource}/${id}`, payload);
    return data.data as SetupRow;
}
export async function deleteSetup(resource: string, id: number): Promise<void> {
    await api.delete(`${base}/${resource}/${id}`);
}

/* ---- Fee structure (configs) --------------------------------------------- */
export interface FeeConfigRow {
    fee_sub_head_id: number;
    fee_sub_head_name: string;
    fee_head_name: string | null;
    amount: string | null;
}
export async function listFeeConfigs(params: { class_config_id: number; academic_year_id: number }): Promise<FeeConfigRow[]> {
    const { data } = await api.get(`${base}/configs`, { params });
    return data.data as FeeConfigRow[];
}
export async function saveFeeConfigs(payload: Record<string, unknown>): Promise<void> {
    await api.post(`${base}/configs`, payload);
}
export async function assessFees(payload: { class_config_id: number; academic_year_id: number }): Promise<{ student_fees_assessed: number }> {
    const { data } = await api.post(`${base}/configs/assess`, payload);
    return data.data;
}

/* ---- Fee time config (due date + flat fine) ------------------------------ */
export interface FeeTimeConfigRow {
    fee_sub_head_id: number;
    fee_sub_head_name: string;
    fee_head_name: string | null;
    due_date: string | null;
    fine_amount: string | null;
}
export async function listFeeTimeConfigs(academicYearId: number): Promise<FeeTimeConfigRow[]> {
    const { data } = await api.get(`${base}/time-configs`, { params: { academic_year_id: academicYearId } });
    return data.data as FeeTimeConfigRow[];
}
export async function saveFeeTimeConfigs(payload: Record<string, unknown>): Promise<void> {
    await api.post(`${base}/time-configs`, payload);
}

/* ---- Waiver assignment ---------------------------------------------------- */
export interface WaiverConfigRow {
    id: number;
    fee_waiver_id: number;
    fee_waiver_name: string;
    fee_sub_head_id: number | null;
    fee_sub_head_name: string;
    academic_year_id: number;
}
export async function listWaiverConfigs(studentId: number): Promise<WaiverConfigRow[]> {
    const { data } = await api.get(`${base}/waiver-configs`, { params: { student_id: studentId } });
    return data.data as WaiverConfigRow[];
}
export async function assignWaiver(payload: Record<string, unknown>): Promise<WaiverConfigRow> {
    const { data } = await api.post(`${base}/waiver-configs`, payload);
    return data.data as WaiverConfigRow;
}
export async function removeWaiver(id: number): Promise<void> {
    await api.delete(`${base}/waiver-configs/${id}`);
}

/* ---- Dues & collection ----------------------------------------------------- */
export interface DueRow {
    student_fee_id: number;
    fee_head_name: string | null;
    fee_sub_head_name: string;
    payable_amount: string;
    waiver_amount: string;
    fine_amount: string;
    paid_amount: string;
    due_date: string | null;
    is_overdue: boolean;
    projected_fine: number;
    due_amount: number;
    status: 'due' | 'partial' | 'paid';
}
export async function studentDues(studentId: number): Promise<DueRow[]> {
    const { data } = await api.get(`${base}/students/${studentId}/dues`);
    return data.data as DueRow[];
}

export interface CollectionRow {
    id: number;
    student_id: number;
    student_name: string;
    receipt_no: string;
    collected_at: string;
    total_amount: string;
    payment_method: string;
    note: string | null;
    voucher_id: number | null;
}
export async function listCollections(params: { student_id?: number; per_page?: number } = {}): Promise<CollectionRow[]> {
    const { data } = await api.get(`${base}/collections`, { params });
    return data.data as CollectionRow[];
}
export async function getCollection(id: number): Promise<CollectionRow & { items: { fee_head_name: string | null; fee_sub_head_name: string; amount: string; fine_paid: string }[]; voucher_no: string | null }> {
    const { data } = await api.get(`${base}/collections/${id}`);
    return data.data;
}
export async function collectFees(payload: {
    student_id: number;
    payment_method: string;
    note?: string;
    items: { student_fee_id: number; amount: number }[];
}): Promise<CollectionRow> {
    const { data } = await api.post(`${base}/collections`, payload);
    return data.data as CollectionRow;
}

/* ---- Reports --------------------------------------------------------------- */
export async function dailyCollectionReport(params: { from: string; to: string }) {
    const { data } = await api.get(`${base}/reports/daily-collection`, { params });
    return data.data as { from: string; to: string; total_collected: number; receipts_count: number; by_head: { fee_head_name: string; amount: number }[] };
}
export async function duesSummaryReport(academicYearId: number) {
    const { data } = await api.get(`${base}/reports/dues-summary`, { params: { academic_year_id: academicYearId } });
    return data.data as { class_config_id: number; class_label: string; students_with_dues: number; total_due: number }[];
}
