import { Pencil, Eye, Trash2, Loader2, Inbox, User } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { ConfirmDialog } from '@/components/Modal';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { type Student } from './api';
import { deleteStudent } from './api';

interface StudentListViewProps {
  students: Student[];
  isLoading: boolean;
  onView: (student: Student) => void;
  onEdit: (student: Student) => void;
  pagination?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
}

export function StudentListView({
  students,
  isLoading,
  onView,
  onEdit,
  pagination,
  onPageChange,
}: StudentListViewProps) {
  const qc = useQueryClient();
  const [deleting, setDeleting] = useState<Student | null>(null);

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteStudent(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['students'] });
      setDeleting(null);
    },
  });

  return (
    <Card>
      <div className="overflow-x-auto">
        {isLoading ? (
          <div className="flex items-center justify-center gap-2 py-16 text-muted">
            <Loader2 size={18} className="animate-spin" /> Loading students…
          </div>
        ) : students.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-surface-2 text-faint">
              <Inbox size={22} />
            </div>
            <p className="text-[14px] font-medium text-fg">No students found</p>
            <p className="text-[13px] text-muted">
              Add your first student or adjust your search filters
            </p>
          </div>
        ) : (
          <>
            <table className="w-full min-w-[900px] text-left text-[13.5px]">
              <thead>
                <tr className="border-b border-border text-[11px] uppercase tracking-wide text-faint">
                  <th className="px-5 py-2.5 font-semibold">Student</th>
                  <th className="px-5 py-2.5 font-semibold">UID</th>
                  <th className="px-5 py-2.5 font-semibold">Class</th>
                  <th className="px-5 py-2.5 font-semibold">Roll</th>
                  <th className="px-5 py-2.5 font-semibold">Guardian</th>
                  <th className="px-5 py-2.5 font-semibold">Contact</th>
                  <th className="px-5 py-2.5 font-semibold">Status</th>
                  <th className="px-5 py-2.5 text-right font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {students.map((student) => (
                  <tr
                    key={student.id}
                    className="border-b border-border last:border-0 hover:bg-surface-2/50"
                  >
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-3">
                        {student.photo_path ? (
                          <img
                            src={student.photo_path}
                            alt={student.name}
                            className="h-8 w-8 rounded-lg object-cover border border-border"
                          />
                        ) : (
                          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-2 text-faint">
                            <User size={16} />
                          </div>
                        )}
                        <div>
                          <div className="font-medium text-fg">{student.name}</div>
                          {student.name_bn && (
                            <div className="text-[12px] text-faint">{student.name_bn}</div>
                          )}
                          <div className="text-[12px] text-muted">
                            {student.fathers_name}'s child
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-3 text-faint">{student.student_uid}</td>
                    <td className="px-5 py-3 text-muted">
                      {student.current_enrollment?.class_config?.name || 'N/A'}
                    </td>
                    <td className="px-5 py-3 text-muted">
                      {student.current_enrollment?.roll || 'N/A'}
                    </td>
                    <td className="px-5 py-3 text-muted">
                      {student.guardians && student.guardians.length > 0 ? (
                        <div className="text-xs">
                          {student.guardians[0].name}
                          {student.guardians.length > 1 && (
                            <span className="text-faint"> +{student.guardians.length - 1}</span>
                          )}
                        </div>
                      ) : (
                        'N/A'
                      )}
                    </td>
                    <td className="px-5 py-3 text-muted">
                      {student.mobile || student.father_mobile || student.mother_mobile ? (
                        <div className="text-xs">
                          {student.mobile && <div>{student.mobile}</div>}
                          {!student.mobile && student.father_mobile && (
                            <div>F: {student.father_mobile}</div>
                          )}
                        </div>
                      ) : (
                        'N/A'
                      )}
                    </td>
                    <td className="px-5 py-3">
                      <Badge tone={student.status === 'active' ? 'success' : 'neutral'}>
                        {student.status}
                      </Badge>
                    </td>
                    <td className="px-5 py-3">
                      <div className="flex justify-end gap-1">
                        <button
                          onClick={() => onView(student)}
                          className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"
                          aria-label="View"
                        >
                          <Eye size={16} />
                        </button>
                        <button
                          onClick={() => onEdit(student)}
                          className="rounded-lg p-1.5 text-faint hover:bg-surface-2 hover:text-brand-600"
                          aria-label="Edit"
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={() => setDeleting(student)}
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
                  {pagination.total} students
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
        title="Delete Student"
        message={`Are you sure you want to delete "${deleting?.name}"? This action can be restored by an administrator.`}
      />
    </Card>
  );
}

// Add useState import
import { useState } from 'react';