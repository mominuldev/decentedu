import { api } from '@/lib/api';

const base = '/api/v1/messaging';

export interface TemplateRow {
    id: number;
    name: string;
    type: 'attendance' | 'result' | 'fee' | 'general' | 'custom';
    message: string;
    status: boolean;
}
export async function listTemplates(): Promise<TemplateRow[]> {
    const { data } = await api.get(`${base}/templates`);
    return data.data as TemplateRow[];
}
export async function createTemplate(payload: Partial<TemplateRow>): Promise<TemplateRow> {
    const { data } = await api.post(`${base}/templates`, payload);
    return data.data as TemplateRow;
}
export async function updateTemplate(id: number, payload: Partial<TemplateRow>): Promise<TemplateRow> {
    const { data } = await api.put(`${base}/templates/${id}`, payload);
    return data.data as TemplateRow;
}
export async function deleteTemplate(id: number): Promise<void> {
    await api.delete(`${base}/templates/${id}`);
}

export interface ContactRow {
    id: number;
    name: string;
    phone: string;
    type: 'student' | 'guardian' | 'employee' | 'custom';
    student_id: number | null;
    employee_id: number | null;
    status: boolean;
}
export async function listContacts(params: { type?: string; search?: string } = {}): Promise<ContactRow[]> {
    const { data } = await api.get(`${base}/contacts`, { params: { per_page: 200, ...params } });
    return data.data as ContactRow[];
}
export async function createContact(payload: Partial<ContactRow>): Promise<ContactRow> {
    const { data } = await api.post(`${base}/contacts`, payload);
    return data.data as ContactRow;
}
export async function updateContact(id: number, payload: Partial<ContactRow>): Promise<ContactRow> {
    const { data } = await api.put(`${base}/contacts/${id}`, payload);
    return data.data as ContactRow;
}
export async function deleteContact(id: number): Promise<void> {
    await api.delete(`${base}/contacts/${id}`);
}

export type AudienceType = 'class' | 'section' | 'contact' | 'custom_numbers';

export interface SendPayload {
    audience_type: AudienceType;
    message: string;
    template_id?: number | null;
    class_id?: number;
    class_config_id?: number;
    contact_ids?: number[];
    numbers?: { phone: string; name?: string }[];
}
export interface BatchRow {
    id: number;
    audience_type: AudienceType;
    message: string;
    total_recipients: number;
    sent_count: number;
    failed_count: number;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    unit_cost: string;
    total_cost: string;
    created_at: string;
    template?: TemplateRow | null;
}
export async function sendSms(payload: SendPayload): Promise<BatchRow> {
    const { data } = await api.post(`${base}/send`, payload);
    return data.data as BatchRow;
}
export async function listBatches(): Promise<BatchRow[]> {
    const { data } = await api.get(`${base}/batches`);
    return data.data as BatchRow[];
}
export interface MessageRow {
    id: number;
    recipient_phone: string;
    recipient_name: string | null;
    status: 'queued' | 'sent' | 'failed';
    gateway_response: string | null;
    sent_at: string | null;
}
export async function getBatch(id: number): Promise<BatchRow & { messages: MessageRow[] }> {
    const { data } = await api.get(`${base}/batches/${id}`);
    return data.data;
}

export async function getBalance(): Promise<number> {
    const { data } = await api.get(`${base}/balance`);
    return Number(data.data.balance);
}
export async function topupBalance(amount: number): Promise<number> {
    const { data } = await api.post(`${base}/balance/topup`, { amount });
    return Number(data.data.balance);
}
