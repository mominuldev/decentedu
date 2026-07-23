import { api } from '@/lib/api';

const base = '/api/v1/credentials';

export interface BranchInfo {
    id: number;
    name: string;
    name_bn: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    logo_path: string | null;
}
export interface SignatureRow {
    id: number;
    position: string;
    person_name: string;
    designation: string;
    image_path: string | null;
}

export interface TransferCertificateRow {
    id: number;
    student_id: number;
    certificate_number: string;
    issue_date: string;
    reason_for_leaving: string | null;
    remarks: string | null;
    academic_year_id: number;
    class_config_id: number;
    student?: { id: number; name: string; student_uid: string };
}
export async function listTransferCertificates(studentId?: number): Promise<TransferCertificateRow[]> {
    const { data } = await api.get(`${base}/transfer-certificates`, { params: { student_id: studentId } });
    return data.data as TransferCertificateRow[];
}
export async function issueTransferCertificate(payload: Record<string, unknown>): Promise<TransferCertificateRow> {
    const { data } = await api.post(`${base}/transfer-certificates`, payload);
    return data.data as TransferCertificateRow;
}
export async function getTransferCertificate(id: number) {
    const { data } = await api.get(`${base}/transfer-certificates/${id}`);
    return data.data as { certificate: TransferCertificateRow & { student: { name: string; student_uid: string; fathers_name: string }; classConfig: { label?: string } }; branch: BranchInfo; signatures: SignatureRow[] };
}

export interface TestimonialRow {
    id: number;
    student_id: number;
    certificate_number: string;
    issue_date: string;
    character_certificate: string | null;
    remarks: string | null;
    academic_year_id: number;
    class_config_id: number;
    student?: { id: number; name: string; student_uid: string };
}
export async function listTestimonials(studentId?: number): Promise<TestimonialRow[]> {
    const { data } = await api.get(`${base}/testimonials`, { params: { student_id: studentId } });
    return data.data as TestimonialRow[];
}
export async function issueTestimonial(payload: Record<string, unknown>): Promise<TestimonialRow> {
    const { data } = await api.post(`${base}/testimonials`, payload);
    return data.data as TestimonialRow;
}
export async function deleteTestimonial(id: number): Promise<void> {
    await api.delete(`${base}/testimonials/${id}`);
}
export async function getTestimonial(id: number) {
    const { data } = await api.get(`${base}/testimonials/${id}`);
    return data.data as { certificate: TestimonialRow & { student: { name: string; student_uid: string; fathers_name: string } }; branch: BranchInfo; signatures: SignatureRow[] };
}

export interface CertificateRow {
    id: number;
    student_id: number;
    certificate_type: 'academic' | 'sports' | 'cultural' | 'attendance' | 'other';
    certificate_number: string;
    issue_date: string;
    description: string | null;
    remarks: string | null;
    student?: { id: number; name: string; student_uid: string };
}
export async function listCertificates(studentId?: number): Promise<CertificateRow[]> {
    const { data } = await api.get(`${base}/certificates`, { params: { student_id: studentId } });
    return data.data as CertificateRow[];
}
export async function issueCertificate(payload: Record<string, unknown>): Promise<CertificateRow> {
    const { data } = await api.post(`${base}/certificates`, payload);
    return data.data as CertificateRow;
}
export async function deleteCertificate(id: number): Promise<void> {
    await api.delete(`${base}/certificates/${id}`);
}
export async function getCertificate(id: number) {
    const { data } = await api.get(`${base}/certificates/${id}`);
    return data.data as { certificate: CertificateRow & { student: { name: string; student_uid: string } }; branch: BranchInfo; signatures: SignatureRow[] };
}

export const ID_CARD_FIELDS = ['photo', 'name', 'roll', 'class', 'designation', 'blood_group', 'address', 'guardian', 'mobile', 'validity', 'signature'] as const;
export type IdCardField = (typeof ID_CARD_FIELDS)[number];

export interface IdCardTemplateRow {
    id: number;
    name: string;
    holder_type: 'student' | 'employee';
    fields: IdCardField[];
    show_qr: boolean;
    primary_color: string | null;
    logo_path: string | null;
    status: boolean;
}
export async function listIdCardTemplates(holderType?: string): Promise<IdCardTemplateRow[]> {
    const { data } = await api.get(`${base}/id-card-templates`, { params: { holder_type: holderType } });
    return data.data as IdCardTemplateRow[];
}
export async function createIdCardTemplate(payload: Partial<IdCardTemplateRow>): Promise<IdCardTemplateRow> {
    const { data } = await api.post(`${base}/id-card-templates`, payload);
    return data.data as IdCardTemplateRow;
}
export async function updateIdCardTemplate(id: number, payload: Partial<IdCardTemplateRow>): Promise<IdCardTemplateRow> {
    const { data } = await api.put(`${base}/id-card-templates/${id}`, payload);
    return data.data as IdCardTemplateRow;
}
export async function deleteIdCardTemplate(id: number): Promise<void> {
    await api.delete(`${base}/id-card-templates/${id}`);
}

export interface CardData {
    photo?: string | null;
    name?: string;
    roll?: number | string | null;
    class?: string | null;
    designation?: string | null;
    blood_group?: string | null;
    address?: string | null;
    guardian?: string | null;
    mobile?: string | null;
}
export async function generateIdCards(payload: { template_id: number; ids?: number[]; class_config_id?: number }) {
    const { data } = await api.post(`${base}/id-cards/generate`, payload);
    return data.data as { template: IdCardTemplateRow; branch: BranchInfo; cards: CardData[] };
}
