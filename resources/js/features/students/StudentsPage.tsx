import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Search, Filter, Loader2, UserPlus, FileText, Mail } from 'lucide-react';
import { Card, Button, Badge } from '@/components/ui';
import { Modal } from '@/components/Modal';
import { useAuth } from '@/features/auth/AuthProvider';
import { listStudents, getStudent, type Student, type StudentFilters } from './api';
import { STATUS_OPTIONS } from './types';
import { StudentForm } from './StudentForm';
import { StudentListView } from './StudentListView';
import { BulkRegisterModal } from './BulkRegisterModal';

export default function StudentsPage() {
  const { session } = useAuth();
  const qc = useQueryClient();

  const [filters, setFilters] = useState<StudentFilters>({
    search: '',
    status: '',
    class_config_id: 0,
    academic_year_id: 0,
  });

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);

  const [showAddModal, setShowAddModal] = useState(false);
  const [showBulkModal, setShowBulkModal] = useState(false);
  const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);
  const [viewMode, setViewMode] = useState<'list' | 'details'>('list');

  const { data: response, isLoading } = useQuery({
    queryKey: ['students', filters, page, perPage],
    queryFn: () => listStudents({
      ...filters,
      search: filters.search || undefined,
      status: filters.status || undefined,
      class_config_id: filters.class_config_id || undefined,
      academic_year_id: filters.academic_year_id || undefined,
      page,
      per_page: perPage,
    }),
  });

  const students = response?.data || [];
  const pagination = response?.meta?.pagination;

  const handleSearch = (value: string) => {
    setFilters(prev => ({ ...prev, search: value }));
    setPage(1);
  };

  const handleFilterChange = (key: keyof StudentFilters, value: string | number) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setPage(1);
  };

  const handleViewStudent = async (student: Student) => {
    try {
      const fullStudent = await getStudent(student.id);
      setSelectedStudent(fullStudent);
      setViewMode('details');
    } catch (error) {
      console.error('Failed to load student details:', error);
    }
  };

  const handleEditStudent = async (student: Student) => {
    try {
      const fullStudent = await getStudent(student.id);
      setSelectedStudent(fullStudent);
      setShowAddModal(true);
    } catch (error) {
      console.error('Failed to load student for edit:', error);
    }
  };

  const refreshList = () => {
    qc.invalidateQueries({ queryKey: ['students'] });
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-[26px] font-bold tracking-tight text-fg">Students</h1>
          <p className="mt-1 text-[14px] text-muted">
            {pagination?.total || 0} total student{pagination?.total === 1 ? '' : 's'}
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setShowBulkModal(true)}>
            <UserPlus size={16} />
            Bulk Register
          </Button>
          <Button onClick={() => { setSelectedStudent(null); setShowAddModal(true); }}>
            <Plus size={16} />
            Add Student
          </Button>
        </div>
      </div>

      {/* Search and Filters */}
      <Card className="p-4">
        <div className="flex flex-wrap items-center gap-4">
          <div className="relative flex-1 min-w-[240px]">
            <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-faint" />
            <input
              type="text"
              placeholder="Search by name, UID, mobile, father's name..."
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
            {STATUS_OPTIONS.map(opt => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>

          <div className="flex items-center gap-2 text-faint">
            <Filter size={18} />
            <span className="text-[13.5px]">Filters active</span>
          </div>
        </div>
      </Card>

      {/* Student List View */}
      {viewMode === 'list' ? (
        <StudentListView
          students={students}
          isLoading={isLoading}
          onView={handleViewStudent}
          onEdit={handleEditStudent}
          pagination={pagination}
          onPageChange={setPage}
          onPerPageChange={setPerPage}
        />
      ) : (
        <StudentDetailsView
          student={selectedStudent!}
          onBack={() => setViewMode('list')}
          onEdit={() => { setShowAddModal(true); }}
          refresh={refreshList}
        />
      )}

      {/* Add/Edit Student Modal */}
      {showAddModal && (
        <StudentForm
          student={selectedStudent}
          onClose={() => { setShowAddModal(false); setSelectedStudent(null); }}
          onSaved={() => { setShowAddModal(false); setSelectedStudent(null); refreshList(); }}
        />
      )}

      {/* Bulk Registration Modal */}
      {showBulkModal && (
        <BulkRegisterModal
          onClose={() => setShowBulkModal(false)}
          onSaved={() => { setShowBulkModal(false); refreshList(); }}
        />
      )}
    </div>
  );
}

// Student Details View Component
function StudentDetailsView({
  student,
  onBack,
  onEdit,
  refresh
}: {
  student: Student;
  onBack: () => void;
  onEdit: () => void;
  refresh: () => void;
}) {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <Button variant="outline" onClick={onBack}>
          ← Back to list
        </Button>
        <Button onClick={onEdit}>
          Edit Student
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main Information */}
        <Card className="lg:col-span-2 p-6">
          <div className="flex items-start gap-6">
            {student.photo_path ? (
              <img
                src={student.photo_path}
                alt={student.name}
                className="h-24 w-24 rounded-2xl object-cover border border-border"
              />
            ) : (
              <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-surface-2 text-faint">
                <FileText size={32} />
              </div>
            )}

            <div className="flex-1">
              <h2 className="text-xl font-semibold text-fg">{student.name}</h2>
              {student.name_bn && <p className="text-faint">{student.name_bn}</p>}
              <div className="mt-2 flex flex-wrap gap-2">
                <Badge tone="brand">UID: {student.student_uid}</Badge>
                <Badge tone={student.status === 'active' ? 'success' : 'neutral'}>
                  {student.status}
                </Badge>
              </div>
            </div>
          </div>

          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            <InfoItem label="Sex" value={student.sex} />
            <InfoItem label="Date of Birth" value={student.dob || 'N/A'} />
            <InfoItem label="Religion" value={student.religion || 'N/A'} />
            <InfoItem label="Blood Group" value={student.blood_group || 'N/A'} />
            <InfoItem label="Mobile" value={student.mobile || 'N/A'} />
            <InfoItem label="Father's Mobile" value={student.father_mobile || 'N/A'} />
            <InfoItem label="Mother's Mobile" value={student.mother_mobile || 'N/A'} />
          </div>

          <div className="mt-6">
            <h3 className="text-sm font-semibold text-fg mb-2">Present Address</h3>
            <p className="text-sm text-muted">{student.present_address || 'N/A'}</p>
          </div>

          <div className="mt-4">
            <h3 className="text-sm font-semibold text-fg mb-2">Permanent Address</h3>
            <p className="text-sm text-muted">{student.permanent_address || 'N/A'}</p>
          </div>
        </Card>

        {/* Current Enrollment & Guardians */}
        <div className="space-y-6">
          {student.current_enrollment && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-fg mb-4">Current Enrollment</h3>
              <div className="space-y-3">
                <InfoItem label="Class" value={student.current_enrollment.class_config?.name || 'N/A'} />
                <InfoItem label="Roll" value={student.current_enrollment.roll} />
                <InfoItem label="Group" value={student.current_enrollment.group_id ? 'Yes' : 'No'} />
                <InfoItem label="Category" value={student.current_enrollment.category_id ? 'Yes' : 'No'} />
              </div>
            </Card>
          )}

          {student.guardians && student.guardians.length > 0 && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-fg mb-4">Guardians</h3>
              <div className="space-y-4">
                {student.guardians.map((guardian) => (
                  <div key={guardian.id} className="border-b border-border pb-3 last:border-0">
                    <div className="flex items-center justify-between">
                      <span className="text-sm font-medium text-fg">{guardian.name}</span>
                      <Badge tone="neutral" size="sm">{guardian.relationship}</Badge>
                    </div>
                    {guardian.mobile && (
                      <div className="mt-1 flex items-center gap-1 text-sm text-muted">
                        <Mail size={14} />
                        {guardian.mobile}
                      </div>
                    )}
                    {guardian.is_emergency_contact && (
                      <Badge tone="brand" size="sm" className="mt-2">Emergency Contact</Badge>
                    )}
                  </div>
                ))}
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-[12.5px] text-muted">{label}</p>
      <p className="text-sm text-fg">{value}</p>
    </div>
  );
}