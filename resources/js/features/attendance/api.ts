import { api } from '@/lib/api';

export type AttendanceStatus = 'present' | 'absent' | 'late' | 'leave' | 'half_day';

export const STATUSES: { value: AttendanceStatus; label: string }[] = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'late', label: 'Late' },
    { value: 'leave', label: 'Leave' },
    { value: 'half_day', label: 'Half day' },
];

export interface Holiday {
    id: number;
    date: string;
    title: string;
    name_bn: string | null;
    type: 'public' | 'weekend' | 'other';
    status: boolean;
}

export interface AttendanceDevice {
    id: number;
    name: string;
    device_uid: string;
    location: string | null;
    ip_address: string | null;
    protocol: 'zkteco' | 'generic';
    status: boolean;
}

export interface DeviceMap {
    id: number;
    attendance_device_id: number;
    device_name: string | null;
    external_user_id: string;
    mappable_type: 'student' | 'employee';
    mappable_id: number;
    mappable_name: string | null;
    status: boolean;
}

export interface TimeConfig {
    id: number;
    applicable_to: 'student' | 'employee';
    class_config_id: number | null;
    class_label: string | null;
    in_time: string;
    out_time: string;
    late_after: string;
    status: boolean;
}

export interface StudentRosterRow {
    student_id: number;
    enrollment_id: number | null;
    roll: string | null;
    name: string;
    name_bn: string | null;
    photo_path: string | null;
    attendance_id: number | null;
    status: AttendanceStatus;
    remarks: string | null;
}

export interface EmployeeRosterRow {
    employee_id: number;
    name: string;
    name_bn: string | null;
    designation_id: number | null;
    attendance_id: number | null;
    status: AttendanceStatus;
    remarks: string | null;
}

export interface AttendanceReportRow {
    student_id?: number;
    employee_id?: number;
    name: string;
    present: number;
    absent: number;
    late: number;
    leave: number;
    half_day: number;
    total_days: number;
}

const base = '/api/v1/attendance';

/* ---- Holidays ---- */
export async function listHolidays(year?: number): Promise<Holiday[]> {
    const { data } = await api.get(`${base}/holidays`, { params: year ? { year } : {} });
    return data.data as Holiday[];
}
export async function createHoliday(payload: Record<string, unknown>): Promise<Holiday> {
    const { data } = await api.post(`${base}/holidays`, payload);
    return data.data as Holiday;
}
export async function updateHoliday(id: number, payload: Record<string, unknown>): Promise<Holiday> {
    const { data } = await api.put(`${base}/holidays/${id}`, payload);
    return data.data as Holiday;
}
export async function deleteHoliday(id: number): Promise<void> {
    await api.delete(`${base}/holidays/${id}`);
}

/* ---- Devices ---- */
export async function listDevices(): Promise<AttendanceDevice[]> {
    const { data } = await api.get(`${base}/devices`);
    return data.data as AttendanceDevice[];
}
export async function createDevice(payload: Record<string, unknown>): Promise<AttendanceDevice> {
    const { data } = await api.post(`${base}/devices`, payload);
    return data.data as AttendanceDevice;
}
export async function updateDevice(id: number, payload: Record<string, unknown>): Promise<AttendanceDevice> {
    const { data } = await api.put(`${base}/devices/${id}`, payload);
    return data.data as AttendanceDevice;
}
export async function deleteDevice(id: number): Promise<void> {
    await api.delete(`${base}/devices/${id}`);
}

/* ---- Device maps ---- */
export async function listDeviceMaps(deviceId?: number): Promise<DeviceMap[]> {
    const { data } = await api.get(`${base}/device-maps`, { params: deviceId ? { attendance_device_id: deviceId } : {} });
    return data.data as DeviceMap[];
}
export async function createDeviceMap(payload: Record<string, unknown>): Promise<DeviceMap> {
    const { data } = await api.post(`${base}/device-maps`, payload);
    return data.data as DeviceMap;
}
export async function deleteDeviceMap(id: number): Promise<void> {
    await api.delete(`${base}/device-maps/${id}`);
}

/* ---- Time configs ---- */
export async function listTimeConfigs(applicableTo?: 'student' | 'employee'): Promise<TimeConfig[]> {
    const { data } = await api.get(`${base}/time-configs`, { params: applicableTo ? { applicable_to: applicableTo } : {} });
    return data.data as TimeConfig[];
}
export async function createTimeConfig(payload: Record<string, unknown>): Promise<TimeConfig> {
    const { data } = await api.post(`${base}/time-configs`, payload);
    return data.data as TimeConfig;
}
export async function updateTimeConfig(id: number, payload: Record<string, unknown>): Promise<TimeConfig> {
    const { data } = await api.put(`${base}/time-configs/${id}`, payload);
    return data.data as TimeConfig;
}
export async function deleteTimeConfig(id: number): Promise<void> {
    await api.delete(`${base}/time-configs/${id}`);
}

/* ---- Punches ---- */
export async function processPunches(): Promise<void> {
    await api.post(`${base}/punches/process`);
}

/* ---- Student attendance ---- */
export async function studentRoster(classConfigId: number, date: string): Promise<StudentRosterRow[]> {
    const { data } = await api.get(`${base}/students`, { params: { class_config_id: classConfigId, date } });
    return data.data as StudentRosterRow[];
}
export async function takeStudentAttendance(
    classConfigId: number,
    date: string,
    entries: { student_id: number; status: AttendanceStatus; remarks?: string | null }[],
): Promise<void> {
    await api.post(`${base}/students/take`, { class_config_id: classConfigId, date, entries });
}
export async function updateStudentAttendance(id: number, payload: { status: AttendanceStatus; remarks?: string | null }): Promise<void> {
    await api.put(`${base}/students/${id}`, payload);
}
export async function studentAttendanceReport(classConfigId: number, from: string, to: string): Promise<AttendanceReportRow[]> {
    const { data } = await api.get(`${base}/students/report`, { params: { class_config_id: classConfigId, from, to } });
    return data.data as AttendanceReportRow[];
}

/* ---- Employee attendance ---- */
export async function employeeRoster(date: string): Promise<EmployeeRosterRow[]> {
    const { data } = await api.get(`${base}/employees`, { params: { date } });
    return data.data as EmployeeRosterRow[];
}
export async function takeEmployeeAttendance(
    date: string,
    entries: { employee_id: number; status: AttendanceStatus; remarks?: string | null }[],
): Promise<void> {
    await api.post(`${base}/employees/take`, { date, entries });
}
export async function updateEmployeeAttendance(id: number, payload: { status: AttendanceStatus; remarks?: string | null }): Promise<void> {
    await api.put(`${base}/employees/${id}`, payload);
}
export async function employeeAttendanceReport(from: string, to: string): Promise<AttendanceReportRow[]> {
    const { data } = await api.get(`${base}/employees/report`, { params: { from, to } });
    return data.data as AttendanceReportRow[];
}
