import { api } from '@/lib/api';

const base = '/api/v1/accounting';

/* ---- Ledger accounts -------------------------------------------------------- */
export interface LedgerAccountRow {
    id: number;
    name: string;
    code: string;
    type: 'asset' | 'liability' | 'income' | 'expense' | 'equity';
    parent_id: number | null;
    is_system: boolean;
    opening_balance: string;
    status: boolean;
}
export async function listLedgerAccounts(type?: string): Promise<LedgerAccountRow[]> {
    const { data } = await api.get(`${base}/ledgers`, { params: type ? { type } : {} });
    return data.data as LedgerAccountRow[];
}
export async function createLedgerAccount(payload: Record<string, unknown>): Promise<LedgerAccountRow> {
    const { data } = await api.post(`${base}/ledgers`, payload);
    return data.data as LedgerAccountRow;
}
export async function updateLedgerAccount(id: number, payload: Record<string, unknown>): Promise<LedgerAccountRow> {
    const { data } = await api.put(`${base}/ledgers/${id}`, payload);
    return data.data as LedgerAccountRow;
}
export async function deleteLedgerAccount(id: number): Promise<void> {
    await api.delete(`${base}/ledgers/${id}`);
}

/* ---- Vouchers ---------------------------------------------------------------- */
export interface VoucherEntryRow { ledger_account_id: number; ledger_account_name: string; debit: string; credit: string }
export interface VoucherRow {
    id: number;
    type: 'receive' | 'payment' | 'contra' | 'journal';
    voucher_no: string;
    date: string;
    note: string | null;
    total: string;
    entries: VoucherEntryRow[];
}
export async function listVouchers(params: { type?: string; from?: string; to?: string; per_page?: number } = {}): Promise<VoucherRow[]> {
    const { data } = await api.get(`${base}/vouchers`, { params });
    return data.data as VoucherRow[];
}
export async function createVoucher(payload: {
    type: string;
    date: string;
    note?: string;
    entries: { ledger_account_id: number; debit: number; credit: number }[];
}): Promise<VoucherRow> {
    const { data } = await api.post(`${base}/vouchers`, payload);
    return data.data as VoucherRow;
}

/* ---- Reports ------------------------------------------------------------------ */
export interface TrialBalanceRow { ledger_account_id: number; name: string; code: string; type: string; debit: number; credit: number; balance: number; balance_side: 'debit' | 'credit' }
export async function trialBalance(params: { from?: string; to?: string } = {}) {
    const { data } = await api.get(`${base}/reports/trial-balance`, { params });
    return data.data as { rows: TrialBalanceRow[]; total_debit: number; total_credit: number };
}
export async function incomeStatement(params: { from?: string; to?: string } = {}) {
    const { data } = await api.get(`${base}/reports/income-statement`, { params });
    return data.data as {
        income: { name: string; code: string; amount: number }[];
        expense: { name: string; code: string; amount: number }[];
        total_income: number;
        total_expense: number;
        net: number;
    };
}
