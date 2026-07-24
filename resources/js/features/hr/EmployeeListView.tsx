import { useState } from 'react';
import { Pencil, Eye, Trash2, Loader2, Inbox, User, Mail } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type Employee } from './api';
import { deleteEmployee } from './api';

interface EmployeeListViewProps {
  employees: Employee[];
  isLoading: boolean;
  onView: (employee: Employee) => void;
  pagination?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
}

export function EmployeeListView({
  employees,
  isLoading,
  onView,
  pagination,
  onPageChange,
}: EmployeeListViewProps) {
  const qc = useQueryClient();
  const [deleting, setDeleting] = useState<Employee | null>(null);

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteEmployee(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['employees'] });
      setDeleting(null);
    },
  });

  return (
    <Card>
      <div className="overflow-x-auto">
        {isLoading ? (
          <div className="flex items-center justify-center gap-2 py-16 text-muted">
            <Loader2 size={18} className="animate-spin" /> Loading employees…
          </div>
        ) : employees.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint">
              <Inbox size={22} />
            </div>
            <p className="text-[14px] font-medium text-fg">No employees found</p>
            <p className="text-[13px] text-muted">
              Add your first employee or adjust your search filters
            </p>
          </div>
        ) : (
          <>
            <table className="w-full min-w-[1000px] text-left text-[13.5px]">
              <thead>
                <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                  <th className="px-5 py-2.5 font-semibold">Employee</th>
                  <th className="px-5 py-2.5 font-semibold">UID</th>
                  <th className="px-5 py-2.5 font-semibold">Designation</th>
                  <th className="px-5 py-2.5 font-semibold">Department</th>
                  <th className="px-5 py-2.5 font-semibold">Contact</th>
                  <th className="px-5 py-2.5 font-semibold">Type</th>
                  <th className="px-5 py-2.5 font-semibold">Status</th>
                  <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {employees.map((employee) => (
                  <tr
                    key={employee.id}
                    className="border-b border-border last:border-0 hover:bg-surface-2/50"
                  >
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-3">
                        {employee.photo_path ? (
                          <img
                            src={employee.photo_path}
                            alt={employee.name}
                            className="h-8 w-8 rounded-lg object-cover border border-border"
                          />
                        ) : (
                          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-2 text-faint">
                            <User size={16} />
                          </div>
                        )}
                        <div>
                          <div className="font-medium text-fg">{employee.name}</div>
                          {employee.name_bn && (
                            <div className="text-[12px] text-faint">{employee.name_bn}</div>
                          )}
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3 text-faint">{employee.employee_uid}</td>
                    <td className="px-5 py-3 text-muted">
                      {employee.designation?.name || 'N/A'}
                    </td>
                    <td className="px-5 py-3 text-muted">
                      {employee.hr_section?.name || 'N/A'}
                    </td>
                    <td className="px-5 py-3 text-muted">
                      {employee.mobile || employee.email ? (
                        <div className="text-xs">
                          {employee.mobile && (
                            <div className="flex items-center gap-1">
                              <Mail size={12} />
                              {employee.mobile}
                            </div>
                          )}
                          {employee.email && (
                            <div className="text-faint truncate">{employee.email}</div>
                          )}
                        </div>
                      ) : (
                        'N/A'
                      )}
                    </td>
                    <td className="px-5 py-3">
                      <Badge tone="neutral" className="text-xs">
                        {employee.employment_type}
                      </Badge>
                    </td>
                    <td className="px-5 py-3">
                      <Badge tone={employee.status === 'active' ? 'success' : 'neutral'}>
                        {employee.status}
                      </Badge>
                    </td>
                    <td className="px-5 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          onClick={() => onView(employee)}
                          className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"
                          aria-label="View"
                        >
                          <Eye size={16} />
                        </button>
                        <button
                          onClick={() => onView(employee)}
                          className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"
                          aria-label="Edit"
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={() => setDeleting(employee)}
                          className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-rose-500"
                          aria-label="Delete"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border px-5 py-3">
                <div className="text-[12.5px] text-muted">
                  Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
                  {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
                  {pagination.total} employees
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(pagination.current_page - 1)}
                    disabled={pagination.current_page === 1}
                  >
                    Previous
                  </Button>
                  <span className="text-[13px] text-muted">
                    Page {pagination.current_page} of {pagination.last_page}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange(pagination.current_page + 1)}
                    disabled={pagination.current_page === pagination.last_page}
                  >
                    Next
                  </Button>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && deleteMutation.mutate(deleting.id)}
        busy={deleteMutation.isPending}
        title="Delete Employee"
        message={`Are you sure you want to delete "${deleting?.name}"? This action can be restored by an administrator.`}
      />
    </Card>
  );
}