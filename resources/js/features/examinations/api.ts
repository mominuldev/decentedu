import { api } from '@/lib/api';

export interface SetupRow {
    id: number;
    name: string;
    name_bn?: string | null;
    type?: string;
    serial: number;
    status: boolean;
    [key: string]: unknown;
}

const base = '/api/v1/examinations';

export async function listSetup(resource: string): Promise<SetupRow[]> {
    const { data } = await api.get(`${base}/${resource}`, { params: { per_page: 200, sort: 'serial' } });
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

/* ---- Grades --------------------------------------------------------------- */
export interface Grade {
    id: number;
    class_id: number;
    name: string;
    grade_point: string;
    mark_from: string;
    mark_to: string;
    serial: number;
    status: boolean;
}
export async function listGrades(classId: number): Promise<Grade[]> {
    const { data } = await api.get(`${base}/grades`, { params: { class_id: classId } });
    return data.data as Grade[];
}
export async function createGrade(payload: Record<string, unknown>): Promise<Grade> {
    const { data } = await api.post(`${base}/grades`, payload);
    return data.data as Grade;
}
export async function updateGrade(id: number, payload: Record<string, unknown>): Promise<Grade> {
    const { data } = await api.put(`${base}/grades/${id}`, payload);
    return data.data as Grade;
}
export async function deleteGrade(id: number): Promise<void> {
    await api.delete(`${base}/grades/${id}`);
}

/* ---- Exam config ------------------------------------------------------------- */
export interface ExamConfig {
    id: number;
    class_id: number;
    class_name: string;
    merit_basis: 'total_mark' | 'grade_point';
    merit_sequential: boolean;
    exam_ids: number[];
    exam_names: string[];
}
export async function listExamConfigs(): Promise<ExamConfig[]> {
    const { data } = await api.get(`${base}/exam-configs`);
    return data.data as ExamConfig[];
}
export async function saveExamConfig(payload: Record<string, unknown>): Promise<ExamConfig> {
    const { data } = await api.post(`${base}/exam-configs`, payload);
    return data.data as ExamConfig;
}
export async function deleteExamConfig(id: number): Promise<void> {
    await api.delete(`${base}/exam-configs/${id}`);
}

/* ---- Mark config ------------------------------------------------------------- */
export interface MarkConfigOptions { subjects: { id: number; name: string }[]; short_codes: { id: number; name: string }[] }
export interface MarkConfigRow {
    id: number;
    class_config_id: number;
    group_id: number | null;
    exam_id: number;
    subject_id: number;
    subject_name: string;
    short_code_id: number;
    short_code_name: string;
    total_marks: string;
    pass_mark: string;
    acceptance: string | null;
    sc_merge: boolean;
    status: boolean;
}
export async function markConfigOptions(): Promise<MarkConfigOptions> {
    const { data } = await api.get(`${base}/mark-configs/options`);
    return data.data;
}
export async function listMarkConfigs(params: { class_config_id: number; exam_id: number; group_id?: number }): Promise<MarkConfigRow[]> {
    const { data } = await api.get(`${base}/mark-configs`, { params });
    return data.data as MarkConfigRow[];
}
export async function saveMarkConfigs(payload: Record<string, unknown>): Promise<MarkConfigRow[]> {
    const { data } = await api.post(`${base}/mark-configs`, payload);
    return data.data as MarkConfigRow[];
}
export async function deleteMarkConfig(id: number): Promise<void> {
    await api.delete(`${base}/mark-configs/${id}`);
}

/* ---- Fourth subject ------------------------------------------------------------- */
export interface FourthSubjectRow { student_id: number; roll: string; name: string; group_id: number | null; subject_id: number | null }
export async function fourthSubjectRoster(params: { academic_year_id: number; class_config_id: number; group_id?: number }): Promise<FourthSubjectRow[]> {
    const { data } = await api.get(`${base}/fourth-subjects`, { params });
    return data.data as FourthSubjectRow[];
}
export async function saveFourthSubjects(payload: Record<string, unknown>): Promise<void> {
    await api.post(`${base}/fourth-subjects`, payload);
}

/* ---- Class teacher config ------------------------------------------------------------- */
export interface ClassTeacherConfigRow { id: number; class_config_id: number; class_label: string; employee_id: number; employee_name: string }
export async function listClassTeacherConfigs(): Promise<ClassTeacherConfigRow[]> {
    const { data } = await api.get(`${base}/class-teacher-configs`);
    return data.data as ClassTeacherConfigRow[];
}
export async function saveClassTeacherConfig(payload: Record<string, unknown>): Promise<ClassTeacherConfigRow> {
    const { data } = await api.post(`${base}/class-teacher-configs`, payload);
    return data.data as ClassTeacherConfigRow;
}
export async function deleteClassTeacherConfig(id: number): Promise<void> {
    await api.delete(`${base}/class-teacher-configs/${id}`);
}

/* ---- Signatures & admit instructions ------------------------------------------------------------- */
export interface SignatureRow { id: number; position: string; person_name: string; designation: string; image_path: string | null; serial: number; status: boolean }
export async function listSignatures(): Promise<SignatureRow[]> {
    const { data } = await api.get(`${base}/signatures`);
    return data.data as SignatureRow[];
}
export async function createSignature(payload: Record<string, unknown>): Promise<SignatureRow> {
    const { data } = await api.post(`${base}/signatures`, payload);
    return data.data as SignatureRow;
}
export async function updateSignature(id: number, payload: Record<string, unknown>): Promise<SignatureRow> {
    const { data } = await api.put(`${base}/signatures/${id}`, payload);
    return data.data as SignatureRow;
}
export async function deleteSignature(id: number): Promise<void> {
    await api.delete(`${base}/signatures/${id}`);
}

export interface AdmitInstructions { instruction1: string | null; instruction2: string | null; instruction3: string | null; instruction4: string | null }
export async function getAdmitInstructions(): Promise<AdmitInstructions> {
    const { data } = await api.get(`${base}/admit-instructions`);
    return data.data;
}
export async function saveAdmitInstructions(payload: AdmitInstructions): Promise<AdmitInstructions> {
    const { data } = await api.put(`${base}/admit-instructions`, payload);
    return data.data;
}

/* ---- Exam routine ------------------------------------------------------------- */
export interface ExamRoutineRow {
    id: number;
    class_config_id: number;
    exam_id: number;
    subject_id: number;
    subject_name: string;
    exam_date: string;
    start_time: string;
    end_time: string;
    room_no: string | null;
    exam_session: string | null;
}
export async function examRoutineOptions(params: { class_config_id: number; exam_id: number; group_id?: number }): Promise<{ subjects: { id: number; name: string }[] }> {
    const { data } = await api.get(`${base}/exam-routine/options`, { params });
    return data.data;
}
export async function listExamRoutine(params: { class_config_id: number; exam_id: number; group_id?: number }): Promise<ExamRoutineRow[]> {
    const { data } = await api.get(`${base}/exam-routine`, { params });
    return data.data as ExamRoutineRow[];
}
export async function createExamRoutine(payload: Record<string, unknown>): Promise<ExamRoutineRow> {
    const { data } = await api.post(`${base}/exam-routine`, payload);
    return data.data as ExamRoutineRow;
}
export async function updateExamRoutine(id: number, payload: Record<string, unknown>): Promise<ExamRoutineRow> {
    const { data } = await api.put(`${base}/exam-routine/${id}`, payload);
    return data.data as ExamRoutineRow;
}
export async function deleteExamRoutine(id: number): Promise<void> {
    await api.delete(`${base}/exam-routine/${id}`);
}

/* ---- Marks ------------------------------------------------------------- */
export interface MarksGrid {
    components: { mark_config_id: number; short_code_name: string; total_marks: string; pass_mark: string }[];
    students: { student_id: number; enrollment_id: number; roll: string; name: string; is_absent: boolean; marks: Record<number, string | null> }[];
}
export async function marksGrid(params: { academic_year_id: number; class_config_id: number; exam_id: number; subject_id: number; group_id?: number }): Promise<MarksGrid> {
    const { data } = await api.get(`${base}/marks/grid`, { params });
    return data.data as MarksGrid;
}
export async function saveMarks(payload: Record<string, unknown>): Promise<void> {
    await api.post(`${base}/marks`, payload);
}

/* ---- Result processing & reports ------------------------------------------------------------- */
export async function generalProcess(payload: Record<string, unknown>): Promise<{ subject_results_processed: number }> {
    const { data } = await api.post(`${base}/results/general-process`, payload);
    return data.data;
}
export async function finalProcess(payload: Record<string, unknown>): Promise<{ subject_results_processed: number }> {
    const { data } = await api.post(`${base}/results/final-process`, payload);
    return data.data;
}
export async function meritProcess(payload: Record<string, unknown>): Promise<{ students_processed: number }> {
    const { data } = await api.post(`${base}/results/merit-process`, payload);
    return data.data;
}

export interface MarksheetRow {
    student_id: number;
    name: string;
    subjects: { subject_name: string; total_marks: string; obtained_marks: string; grade: string | null; grade_point: string | null; is_pass: boolean; is_absent: boolean }[];
    total_marks: string | null;
    total_obtained: string | null;
    gpa: string | null;
    is_pass: boolean | null;
    class_position: number | null;
    section_position: number | null;
}
export async function marksheet(params: { class_config_id: number; exam_id: number }): Promise<MarksheetRow[]> {
    const { data } = await api.get(`${base}/results/marksheet`, { params });
    return data.data as MarksheetRow[];
}

export interface TabulationSheet {
    subjects: { id: number; name: string }[];
    rows: { student_id: number; name: string; marks: Record<number, string | number> }[];
}
export async function tabulationSheet(params: { class_config_id: number; exam_id: number }): Promise<TabulationSheet> {
    const { data } = await api.get(`${base}/results/tabulation-sheet`, { params });
    return data.data as TabulationSheet;
}

export interface MeritRow { student_id: number; name: string; section: string | null; total_obtained: string; gpa: string | null; position: number | null }
export async function meritList(params: { exam_id: number; class_id?: number; class_config_id?: number }): Promise<MeritRow[]> {
    const { data } = await api.get(`${base}/results/merit-list`, { params });
    return data.data as MeritRow[];
}

export interface FailRow { student_id: number; name: string; section: string | null; total_obtained: string; failed_subjects: string[] }
export async function failList(params: { exam_id: number; class_id?: number; class_config_id?: number }): Promise<FailRow[]> {
    const { data } = await api.get(`${base}/results/fail-list`, { params });
    return data.data as FailRow[];
}
