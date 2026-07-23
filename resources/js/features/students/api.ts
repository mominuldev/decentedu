import { api } from '@/lib/api';

// Student types
export interface Student {
  id: number;
  student_uid: string;
  name: string;
  name_bn: string | null;
  sex: 'male' | 'female' | 'other';
  religion: string | null;
  blood_group: string | null;
  dob: string | null;
  fathers_name: string;
  mothers_name: string;
  mobile: string | null;
  father_mobile: string | null;
  mother_mobile: string | null;
  photo_path: string | null;
  present_address: string | null;
  permanent_address: string | null;
  status: 'active' | 'transferred' | 'left' | 'passed_out';
  created_at: string;
  updated_at: string;
  current_enrollment: Enrollment | null;
  guardians: Guardian[];
}

export interface Enrollment {
  id: number;
  student_id: number;
  academic_year_id: number;
  class_config_id: number;
  group_id: number | null;
  category_id: number | null;
  roll: string;
  is_current: boolean;
  enrolled_at: string;
  left_at: string | null;
  class_config?: {
    id: number;
    name: string;
  };
}

export interface Guardian {
  id: number;
  student_id: number;
  relationship: 'father' | 'mother' | 'guardian' | 'other';
  name: string;
  mobile: string | null;
  email: string | null;
  address: string | null;
  occupation: string | null;
  nid: string | null;
  is_emergency_contact: boolean;
  created_at: string;
}

export interface StudentListResponse {
  data: Student[];
  meta: {
    pagination: {
      total: number;
      per_page: number;
      current_page: number;
      last_page: number;
    };
  };
}

export interface CreateStudentRequest {
  student_uid: string;
  name: string;
  name_bn?: string;
  sex: 'male' | 'female' | 'other';
  religion?: string;
  blood_group?: string;
  dob?: string;
  fathers_name: string;
  mothers_name: string;
  mobile?: string;
  father_mobile?: string;
  mother_mobile?: string;
  photo_path?: string;
  present_address?: string;
  permanent_address?: string;
  status?: 'active' | 'transferred' | 'left' | 'passed_out';
  // Initial enrollment
  academic_year_id: number;
  class_config_id: number;
  group_id?: number;
  category_id?: number;
  roll: string;
  // Guardians
  guardians?: CreateGuardianRequest[];
}

export interface CreateGuardianRequest {
  relationship: 'father' | 'mother' | 'guardian' | 'other';
  name: string;
  mobile?: string;
  email?: string;
  address?: string;
  occupation?: string;
  nid?: string;
  is_emergency_contact?: boolean;
}

export interface BulkRegisterRequest {
  academic_year_id: number;
  class_config_id: number;
  students: {
    student_uid: string;
    name: string;
    sex: 'male' | 'female' | 'other';
    fathers_name: string;
    mothers_name: string;
    roll: string;
    mobile?: string;
  }[];
}

export interface BulkRegisterResponse {
  data: {
    created: Array<{ index: number; student_uid: string; id: number }>;
    failed: Array<{ index: number; student_uid: string; error: string }>;
    summary: {
      total: number;
      created_count: number;
      failed_count: number;
    };
  };
}

const base = '/api/v1/students';

export async function listStudents(params?: {
  search?: string;
  status?: string;
  class_config_id?: number;
  academic_year_id?: number;
  page?: number;
  per_page?: number;
  sort?: string;
}): Promise<StudentListResponse> {
  const { data } = await api.get(base, { params });
  return data as StudentListResponse;
}

export async function getStudent(id: number): Promise<Student> {
  const { data } = await api.get(`${base}/${id}`);
  return data.data as Student;
}

export async function createStudent(payload: CreateStudentRequest): Promise<Student> {
  const { data } = await api.post(base, payload);
  return data.data as Student;
}

export async function updateStudent(id: number, payload: Partial<CreateStudentRequest>): Promise<Student> {
  const { data } = await api.put(`${base}/${id}`, payload);
  return data.data as Student;
}

export async function deleteStudent(id: number): Promise<void> {
  await api.delete(`${base}/${id}`);
}

export async function bulkRegister(payload: BulkRegisterRequest): Promise<BulkRegisterResponse> {
  const { data } = await api.post(`${base}/bulk-register`, payload);
  return data as BulkRegisterResponse;
}

export async function migrateStudents(payload: {
  from_academic_year_id: number;
  to_academic_year_id: number;
  student_ids: number[];
  migration_type: 'promote' | 'push_back' | 'general';
}): Promise<{ message: string }> {
  const { data } = await api.post(`${base}/migrate`, payload);
  return data;
}