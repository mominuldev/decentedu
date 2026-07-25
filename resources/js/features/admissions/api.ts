import { api } from '@/lib/api';

export type ApplicationStatus = 'pending' | 'selected' | 'waiting' | 'rejected' | 'admitted';

export interface AdmissionYear {
  id: number;
  branch_id: number;
  academic_year_id: number | null;
  title: string;
  start_date: string | null;
  end_date: string | null;
  status: 'open' | 'closed';
  serial: number;
  applications_count?: number;
  academic_year?: { id: number; name: string } | null;
}

export interface Quota {
  id: number;
  branch_id: number;
  name: string;
  description: string | null;
  capacity: number | null;
  status: boolean;
  serial: number;
  applications_count?: number;
}

export interface AdmissionApplication {
  id: number;
  admission_year_id: number;
  class_config_id: number;
  quota_id: number | null;
  application_no: string;
  name: string;
  name_bn: string | null;
  sex: 'male' | 'female' | 'other';
  religion: string | null;
  blood_group: string | null;
  dob: string | null;
  fathers_name: string;
  mothers_name: string;
  mobile: string | null;
  guardian_mobile: string | null;
  photo_path: string | null;
  present_address: string | null;
  permanent_address: string | null;
  score: number | null;
  status: ApplicationStatus;
  student_id: number | null;
  applied_at: string | null;
  remarks: string | null;
  created_at: string;
  updated_at: string;
  admission_year?: { id: number; title: string; status: string };
  class_config?: { id: number; name: string };
  quota?: { id: number; name: string };
}

export interface Pagination {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface ApplicationListResponse {
  data: AdmissionApplication[];
  meta: { pagination: Pagination };
}

export interface AdmissionStats {
  total: number;
  pending: number;
  selected: number;
  waiting: number;
  rejected: number;
  admitted: number;
}

const base = '/api/v1/admissions';

/* ---- Admission years ----------------------------------------------------- */
export async function listYears(): Promise<AdmissionYear[]> {
  const { data } = await api.get(`${base}/years`);
  return data.data as AdmissionYear[];
}
export async function createYear(payload: Partial<AdmissionYear>): Promise<AdmissionYear> {
  const { data } = await api.post(`${base}/years`, payload);
  return data.data as AdmissionYear;
}
export async function updateYear(id: number, payload: Partial<AdmissionYear>): Promise<AdmissionYear> {
  const { data } = await api.put(`${base}/years/${id}`, payload);
  return data.data as AdmissionYear;
}
export async function deleteYear(id: number): Promise<void> {
  await api.delete(`${base}/years/${id}`);
}

/* ---- Quotas -------------------------------------------------------------- */
export async function listQuotas(): Promise<Quota[]> {
  const { data } = await api.get(`${base}/quotas`);
  return data.data as Quota[];
}
export async function createQuota(payload: Partial<Quota>): Promise<Quota> {
  const { data } = await api.post(`${base}/quotas`, payload);
  return data.data as Quota;
}
export async function updateQuota(id: number, payload: Partial<Quota>): Promise<Quota> {
  const { data } = await api.put(`${base}/quotas/${id}`, payload);
  return data.data as Quota;
}
export async function deleteQuota(id: number): Promise<void> {
  await api.delete(`${base}/quotas/${id}`);
}

/* ---- Applications -------------------------------------------------------- */
export interface ApplicationFilters {
  search?: string;
  admission_year_id?: number;
  class_config_id?: number;
  quota_id?: number;
  status?: string;
  sort?: string;
  page?: number;
  per_page?: number;
}

export async function listApplications(params?: ApplicationFilters): Promise<ApplicationListResponse> {
  const { data } = await api.get(`${base}/applications`, { params });
  return data as ApplicationListResponse;
}
export async function getApplication(id: number): Promise<AdmissionApplication> {
  const { data } = await api.get(`${base}/applications/${id}`);
  return data.data as AdmissionApplication;
}
export async function createApplication(payload: Record<string, unknown>): Promise<AdmissionApplication> {
  const { data } = await api.post(`${base}/applications`, payload);
  return data.data as AdmissionApplication;
}
export async function updateApplication(id: number, payload: Record<string, unknown>): Promise<AdmissionApplication> {
  const { data } = await api.put(`${base}/applications/${id}`, payload);
  return data.data as AdmissionApplication;
}
export async function setApplicationStatus(id: number, status: ApplicationStatus, remarks?: string): Promise<AdmissionApplication> {
  const { data } = await api.post(`${base}/applications/${id}/status`, { status, remarks });
  return data.data as AdmissionApplication;
}
export async function convertApplication(id: number, payload: {
  student_uid: string;
  academic_year_id: number;
  class_config_id?: number;
  group_id?: number;
  category_id?: number;
  roll: string;
}): Promise<{ student_id: number; student_uid: string; application: AdmissionApplication }> {
  const { data } = await api.post(`${base}/applications/${id}/convert`, payload);
  return data.data;
}
export async function deleteApplication(id: number): Promise<void> {
  await api.delete(`${base}/applications/${id}`);
}
export async function getStats(admissionYearId?: number): Promise<AdmissionStats> {
  const { data } = await api.get(`${base}/applications/stats`, {
    params: admissionYearId ? { admission_year_id: admissionYearId } : undefined,
  });
  return data.data as AdmissionStats;
}
