import { api } from '@/lib/api';

export interface SetupRow {
    id: number;
    name: string;
    name_bn: string | null;
    serial: number;
    status: boolean;
    code?: string | null;
    is_current?: boolean;
    [key: string]: unknown;
}

export interface ClassConfigRow {
    id: number;
    class_id: number;
    shift_id: number;
    section_id: number;
    class_name: string;
    shift_name: string;
    section_name: string;
    label: string;
    serial: number;
    status: boolean;
}

export interface Option { id: number; name: string }
export interface ConfigOptions { classes: Option[]; shifts: Option[]; sections: Option[]; groups: Option[] }

const base = '/api/v1/academic';

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

export async function listClassConfigs(): Promise<ClassConfigRow[]> {
    const { data } = await api.get(`${base}/class-configs`);
    return data.data as ClassConfigRow[];
}

export async function classConfigOptions(): Promise<ConfigOptions> {
    const { data } = await api.get(`${base}/class-configs/options`);
    return data.data;
}

export async function createClassConfig(payload: Record<string, unknown>): Promise<ClassConfigRow> {
    const { data } = await api.post(`${base}/class-configs`, payload);
    return data.data as ClassConfigRow;
}

export async function updateClassConfig(id: number, payload: Record<string, unknown>): Promise<ClassConfigRow> {
    const { data } = await api.put(`${base}/class-configs/${id}`, payload);
    return data.data as ClassConfigRow;
}

export async function deleteClassConfig(id: number): Promise<void> {
    await api.delete(`${base}/class-configs/${id}`);
}
