import { api } from '@/lib/api';

export interface AuditLogRow {
    id: number;
    user: string | null;
    auditable_type: string;
    auditable_id: number;
    action: string;
    changes: Record<string, unknown> | null;
    created_at: string;
}
export interface AuditLogPage {
    rows: AuditLogRow[];
    pagination: { total: number; per_page: number; current_page: number; last_page: number };
}

export async function listAuditLogs(page = 1): Promise<AuditLogPage> {
    const { data } = await api.get('/api/v1/audit-logs', { params: { page, per_page: 25 } });
    return { rows: data.data as AuditLogRow[], pagination: data.meta.pagination };
}
