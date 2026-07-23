export interface StudentFormData {
  student_uid: string;
  name: string;
  name_bn: string;
  sex: 'male' | 'female' | 'other';
  religion: string;
  blood_group: string;
  dob: string;
  fathers_name: string;
  mothers_name: string;
  mobile: string;
  father_mobile: string;
  mother_mobile: string;
  photo_path: string;
  present_address: string;
  permanent_address: string;
  status: 'active' | 'transferred' | 'left' | 'passed_out';
  // Enrollment data
  academic_year_id: number;
  class_config_id: number;
  group_id: number;
  category_id: number;
  roll: string;
  // Guardians
  guardians: GuardianFormData[];
}

export interface GuardianFormData {
  relationship: 'father' | 'mother' | 'guardian' | 'other';
  name: string;
  mobile: string;
  email: string;
  address: string;
  occupation: string;
  nid: string;
  is_emergency_contact: boolean;
}

export interface StudentFilters {
  search: string;
  status: string;
  class_config_id: number;
  academic_year_id: number;
}

export const GENDER_OPTIONS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' },
] as const;

export const STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'transferred', label: 'Transferred' },
  { value: 'left', label: 'Left' },
  { value: 'passed_out', label: 'Passed Out' },
] as const;

export const RELATIONSHIP_OPTIONS = [
  { value: 'father', label: 'Father' },
  { value: 'mother', label: 'Mother' },
  { value: 'guardian', label: 'Guardian' },
  { value: 'other', label: 'Other' },
] as const;

export const BLOOD_GROUP_OPTIONS = [
  { value: 'A+', label: 'A+' },
  { value: 'A-', label: 'A-' },
  { value: 'B+', label: 'B+' },
  { value: 'B-', label: 'B-' },
  { value: 'AB+', label: 'AB+' },
  { value: 'AB-', label: 'AB-' },
  { value: 'O+', label: 'O+' },
  { value: 'O-', label: 'O-' },
] as const;