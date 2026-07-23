import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Search, Filter, Loader2, UserPlus, Briefcase, Users } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal } from '@/components/Modal';
import { listEmployees, getEmployee, type Employee, type EmployeeFilters } from './api';
import { EmployeeListView } from './EmployeeListView';
import { EmployeeForm } from './EmployeeForm';
import { SetupResourcesPanel } from './SetupResourcesPanel';

export default function HrPage() {
  const qc = useQueryClient();

  const [activeTab, setActiveTab] = useState<'employees' | 'setup'>('employees');

  const [filters, setFilters] = useState<EmployeeFilters>({
    search: '',
    status: '',
    designation_id: 0,
    employment_type: '',
  });

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);

  const [showAddModal, setShowAddModal] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState<Employee | null>(null);

  const { data: response, isLoading } = useQuery({
    queryKey: ['employees', filters, page, perPage],
    queryFn: () => listEmployees({
      ...filters,
      search: filters.search || undefined,
      status: filters.status || undefined,
      designation_id: filters.designation_id || undefined,
      employment_type: filters.employment_type || undefined,
      page,
      per_page: perPage,
    }),
    enabled: activeTab === 'employees',
  });

  const employees = response?.data || [];
  const pagination = response?.meta?.pagination;

  const handleSearch = (value: string) => {
    setFilters(prev => ({ ...prev, search: value }));
    setPage(1);
  };

  const handleFilterChange = (key: keyof EmployeeFilters, value: string | number) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setPage(1);
  };

  const handleViewEmployee = async (employee: Employee) => {
    try {
      const fullEmployee = await getEmployee(employee.id);
      setSelectedEmployee(fullEmployee);
      setShowAddModal(true);
    } catch (error) {
      console.error('Failed to load employee details:', error);
    }
  };

  const refreshList = () => {
    qc.invalidateQueries({ queryKey: ['employees'] });
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[26px] font-bold tracking-tight text-fg">HR & Staff</h1>
          <p className="mt-1 text-[14px] text-muted">
            {activeTab === 'employees' ? `${pagination?.total || 0} total employee${pagination?.total === 1 ? '' : 's'}` : 'Setup HR resources'}
          </p>
        </div>
        {activeTab === 'employees' && (
          <Button onClick={() => { setSelectedEmployee(null); setShowAddModal(true); }}>
            <Plus size={16} />
            Add Employee
          </Button>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-border">
        <button
          onClick={() => setActiveTab('employees')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
            activeTab === 'employees'
              ? 'border-brand-600 text-brand-700'
              : 'border-transparent text-muted hover:text-fg'
          }`}
        >
          <Users size={18} />
          Employees
        </button>
        <button
          onClick={() => setActiveTab('setup')}
          className={`flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors ${
            activeTab === 'setup'
              ? 'border-brand-600 text-brand-700'
              : 'border-transparent text-muted hover:text-fg'
          }`}
        >
          <Briefcase size={18} />
          Setup
        </button>
      </div>

      {activeTab === 'employees' ? (
        <>
          {/* Search and Filters */}
          <Card className="p-4">
            <div className="flex flex-wrap items-center gap-4">
              <div className="relative flex-1 min-w-[240px]">
                <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
                <input
                  type="text"
                  placeholder="Search by name, UID, mobile, email..."
                  value={filters.search}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="w-full rounded-xl border border-border-strong bg-surface pl-10 pr-4 py-2.5 text-[14px] text-fg outline-none placeholder:text-faint focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
                />
              </div>

              <select
                value={filters.status}
                onChange={(e) => handleFilterChange('status', e.target.value)}
                className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
              >
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="resigned">Resigned</option>
                <option value="terminated">Terminated</option>
                <option value="retired">Retired</option>
              </select>

              <select
                value={filters.employment_type}
                onChange={(e) => handleFilterChange('employment_type', e.target.value)}
                className="rounded-xl border border-border-strong bg-surface px-3.5 py-2.5 text-[14px] text-fg outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25"
              >
                <option value="">All Types</option>
                <option value="permanent">Permanent</option>
                <option value="contract">Contract</option>
                <option value="temporary">Temporary</option>
              </select>

              <div className="flex items-center gap-2 text-faint">
                <Filter size={18} />
                <span className="text-[13.5px]">Filters active</span>
              </div>
            </div>
          </Card>

          {/* Employee List View */}
          <EmployeeListView
            employees={employees}
            isLoading={isLoading}
            onView={handleViewEmployee}
            pagination={pagination}
            onPageChange={setPage}
            onPerPageChange={setPerPage}
          />
        </>
      ) : (
        <SetupResourcesPanel />
      )}

      {/* Add/Edit Employee Modal */}
      {showAddModal && (
        <EmployeeForm
          employee={selectedEmployee}
          onClose={() => { setShowAddModal(false); setSelectedEmployee(null); }}
          onSaved={() => { setShowAddModal(false); setSelectedEmployee(null); refreshList(); }}
        />
      )}
    </div>
  );
}