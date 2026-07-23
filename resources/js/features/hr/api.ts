import { api } from '@/lib/api';

// HR types
export interface Employee {
  id: number;
  employee_uid: string;
  name: string;
  name_bn: string | null;
  sex: 'male' | 'female' | 'other';
  religion: string | null;
  blood_group: string | null;
  dob: string | null;
  mobile: string | null;
  email: string | null;
  nid: string | null;
  photo_path: string | null;
  present_address: string | null;
  permanent_address: string | null;
  joining_date: string;
  leaving_date: string | null;
  employment_type: 'permanent' | 'contract' | 'temporary';
  status: 'active' | 'resigned' | 'terminated' | 'retired';
  qualifications: string[] | null;
  created_at: string;
  updated_at: string;
  designation?: {
    id: number;
    name: string;
  };
  hr_section?: {
    id: number;
    name: string;
  };
  subject_teachers?: SubjectTeacher[];
}

export interface SubjectTeacher {
  id: number;
  employee_id: number;
  subject_id: number;
  class_config_id: number;
  is_active: boolean;
  created_at: string;
  subject?: {
    id: number;
    name: string;
  };
  class_config?: {
    id: number;
    name: string;
  };
}

export interface Designation {
  id: number;
  name: string;
  name_bn: string | null;
  serial: number;
  status: boolean;
  description: string | null;
}

export interface HrSection {
  id: number;
  name: string;
  name_bn: string | null;
  serial: number;
  status: boolean;
  description: string | null;
}

export interface EmployeeListResponse {
  data: Employee[];
  meta: {
    pagination: {
      total: number;
      per_page: number;
      current_page: number;
      last_page: number;
    };
  };
}

export interface CreateEmployeeRequest {
  employee_uid: string;
  name: string;
  name_bn?: string;
  designation_id: number;
  hr_section_id?: number;
  sex: 'male' | 'female' | 'other';
  religion?: string;
  dob?: string;
  mobile?: string;
  email?: string;
  nid?: string;
  photo_path?: string;
  present_address?: string;
  permanent_address?: string;
  joining_date: string;
  employment_type?: 'permanent' | 'contract' | 'temporary';
  qualifications?: string[];
  subject_assignments?: Array<{
    subject_id: number;
    class_config_id: number;
  }>;
}

const base = '/api/v1/hr';

export async function listEmployees(params?: {
  search?: string;
  status?: string;
  designation_id?: number;
  hr_section_id?: number;
  employment_type?: string;
  teachers_only?: boolean;
  page?: number;
  per_page?: number;
  sort?: string;
}): Promise<EmployeeListResponse> {
  const { data } = await api.get(`${base}/employees`, { params });
  return data as EmployeeListResponse;
}

export async function getEmployee(id: number): Promise<Employee> {
  const { data } = await api.get(`${base}/employees/${id}`);
  return data.data as Employee;
}

export async function createEmployee(payload: CreateEmployeeRequest): Promise<Employee> {
  const { data } = await api.post(`${base}/employees`, payload);
  return data.data as Employee;
}

export async function updateEmployee(id: number, payload: Partial<CreateEmployeeRequest>): Promise<Employee> {
  const { data } = await api.put(`${base}/employees/${id}`, payload);
  return data.data as Employee;
}

export async function deleteEmployee(id: number): Promise<void> {
  await api.delete(`${base}/employees/${id}`);
}

export async function assignSubjectToTeacher(
  employeeId: number,
  payload: { subject_id: number; class_config_id: number }
): Promise<SubjectTeacher> {
  const { data } = await api.post(`${base}/employees/${employeeId}/assign-subject`, payload);
  return data.data as SubjectTeacher;
}

export async function removeSubjectAssignment(employeeId: number, assignmentId: number): Promise<void> {
  await api.delete(`${base}/employees/${employeeId}/subject-assignments/${assignmentId}`);
}

// Setup resources
export async function listDesignations(): Promise<Designation[]> {
  const { data } = await api.get(`${base}/designations`, { params: { per_page: 200, sort: 'serial' } });
  return data.data as Designation[];
}

export async function listHrSections(): Promise<HrSection[]> {
  const { data } = await api.get(`${base}/hr-sections`, { params: { per_page: 200, sort: 'serial' } });
  return data.data as HrSection[];
}

export async function createDesignation(payload: {
  name: string;
  name_bn?: string;
  serial?: number;
  status?: boolean;
  description?: string;
}): Promise<Designation> {
  const { data } = await api.post(`${base}/designations`, payload);
  return data.data as Designation;
}

export async function createHrSection(payload: {
  name: string;
  name_bn?: string;
  serial?: number;
  status?: boolean;
  description?: string;
}): Promise<HrSection> {
  const { data } = await api.post(`${base}/hr-sections`, payload);
  return data.data as HrSection;
}