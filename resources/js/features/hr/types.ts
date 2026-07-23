export interface EmployeeFormData {
  employee_uid: string;
  name: string;
  name_bn: string;
  sex: 'male' | 'female' | 'other';
  religion: string;
  dob: string;
  designation_id: number;
  hr_section_id: number;
  mobile: string;
  email: string;
  nid: string;
  photo_path: string;
  present_address: string;
  permanent_address: string;
  joining_date: string;
  leaving_date: string;
  employment_type: 'permanent' | 'contract' | 'temporary';
  status: 'active' | 'resigned' | 'terminated' | 'retired';
  qualifications: string[];
}

export interface EmployeeFilters {
  search: string;
  status: string;
  designation_id: number;
  employment_type: string;
}

export const EMPLOYMENT_TYPE_OPTIONS = [
  { value: 'permanent', label: 'Permanent' },
  { value: 'contract', label: 'Contract' },
  { value: 'temporary', label: 'Temporary' },
] as const;

export const EMPLOYEE_STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'resigned', label: 'Resigned' },
  { value: 'terminated', label: 'Terminated' },
  { value: 'retired', label: 'Retired' },
] as const;

// Import shared options from students types for reuse
export const GENDER_OPTIONS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' },
] as const;

export const STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'resigned', label: 'Resigned' },
  { value: 'terminated', label: 'Terminated' },
  { value: 'retired', label: 'Retired' },
] as const;