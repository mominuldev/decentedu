import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Search, Download, Loader2, CheckCircle, XCircle, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui';
import { api, toApiError } from '@/lib/api';
import { cn } from '@/lib/cn';

interface ResultOptions {
  academic_years: Array<{ id: number; name: string; is_current: boolean }>;
  sections: Array<{ id: number; name: string }>;
  class_configs: Array<{
    id: number;
    label: string;
    class_name: string;
    section_name: string;
    section_id: number | null;
    shift_name: string;
  }>;
  exams: Array<{ id: number; name: string }>;
}

interface StudentResult {
  student: {
    id: number;
    name: string;
    name_bn: string | null;
    student_uid: string;
    roll_no: string;
    class_name: string;
    section_name: string;
    shift_name: string;
    fathers_name: string | null;
    mothers_name: string | null;
  };
  exam: {
    id: number;
    name: string;
  };
  academic_year: {
    id: number;
    name: string;
  };
  summary: {
    total_marks: number;
    total_obtained: number;
    gpa: number | string;
    is_pass: boolean;
    class_position: number | null;
    section_position: number | null;
  } | null;
  subjects: Array<{
    subject_name: string;
    subject_code: string;
    total_marks: number;
    obtained_marks: number;
    grade: string;
    grade_point: number | string;
    is_pass: boolean;
    is_absent: boolean;
  }>;
}

interface SearchFormData {
  academic_year_id: string;
  class_config_id: string;
  section_id: string;
  exam_id: string;
  roll_no: string;
}

export function PublicResultSearch() {
  const [formData, setFormData] = useState<SearchFormData>({
    academic_year_id: '',
    class_config_id: '',
    section_id: '',
    exam_id: '',
    roll_no: '',
  });

  const [searchResult, setSearchResult] = useState<StudentResult | null>(null);
  const [isSearching, setIsSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [isDownloading, setIsDownloading] = useState(false);

  // Fetch options for dropdowns
  const { data: options, isLoading: isLoadingOptions } = useQuery<ResultOptions>({
    queryKey: ['public-results', 'options'],
    queryFn: async () => {
      const res = await api.get('/api/v1/site/results/options');
      return res.data.data;
    },
  });

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.academic_year_id || !formData.exam_id || !formData.roll_no.trim()) {
      setSearchError('Please fill in all required fields');
      return;
    }

    // Validate that at least class or section is selected
    if (!formData.class_config_id && !formData.section_id) {
      setSearchError('Please select either Class/Section or Section');
      return;
    }

    setIsSearching(true);
    setSearchError(null);
    setSearchResult(null);

    try {
      const res = await api.post('/api/v1/site/results/search', {
        academic_year_id: Number(formData.academic_year_id),
        class_config_id: formData.class_config_id ? Number(formData.class_config_id) : null,
        section_id: formData.section_id ? Number(formData.section_id) : null,
        exam_id: Number(formData.exam_id),
        roll_no: formData.roll_no.trim(),
      });

      setSearchResult(res.data.data);
    } catch (err) {
      const error = toApiError(err);
      setSearchError(error.message || 'Failed to search for results');
    } finally {
      setIsSearching(false);
    }
  };

  const handleDownloadPdf = async () => {
    if (!searchResult) return;

    setIsDownloading(true);
    try {
      const response = await api.post('/api/v1/site/results/marksheet/pdf', {
        academic_year_id: searchResult.academic_year.id,
        class_config_id: formData.class_config_id ? Number(formData.class_config_id) : null,
        section_id: formData.section_id ? Number(formData.section_id) : null,
        exam_id: searchResult.exam.id,
        roll_no: searchResult.student.roll_no,
      }, {
        responseType: 'blob',
      });

      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `marksheet-${searchResult.student.roll_no}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Failed to download PDF:', err);
      alert('Failed to download PDF. Please try again.');
    } finally {
      setIsDownloading(false);
    }
  };

  if (isLoadingOptions) {
    return (
      <div className="flex items-center justify-center py-24 text-faint">
        <Loader2 size={24} className="animate-spin" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="rounded-lg border border-border bg-bg p-6">
        <form onSubmit={handleSearch} className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            {/* Academic Year */}
            <div className="space-y-2">
              <label className="text-sm font-medium text-fg">Academic Year *</label>
              <select
                value={formData.academic_year_id}
                onChange={(e) => setFormData({ ...formData, academic_year_id: e.target.value })}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-fg focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                required
              >
                <option value="">Select Year</option>
                {options?.academic_years.map((year) => (
                  <option key={year.id} value={year.id}>
                    {year.name} {year.is_current && '(Current)'}
                  </option>
                ))}
              </select>
            </div>

            {/* Section Filter */}
            <div className="space-y-2">
              <label className="text-sm font-medium text-fg">Section (A,B,C,D)</label>
              <select
                value={formData.section_id}
                onChange={(e) => setFormData({ ...formData, section_id: e.target.value, class_config_id: '' })}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-fg focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
              >
                <option value="">All Sections</option>
                {options?.sections.map((section) => (
                  <option key={section.id} value={section.id}>
                    {section.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Class & Section (Optional, populated when section is selected) */}
            <div className="space-y-2">
              <label className="text-sm font-medium text-fg">Specific Class/Section</label>
              <select
                value={formData.class_config_id}
                onChange={(e) => setFormData({ ...formData, class_config_id: e.target.value, section_id: '' })}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-fg focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
              >
                <option value="">Select Specific Class</option>
                {options?.class_configs.map((config) => (
                  <option key={config.id} value={config.id}>
                    {config.label}
                  </option>
                ))}
              </select>
              <p className="text-xs text-muted">Leave empty to search by section only</p>
            </div>

            {/* Exam */}
            <div className="space-y-2">
              <label className="text-sm font-medium text-fg">Exam</label>
              <select
                value={formData.exam_id}
                onChange={(e) => setFormData({ ...formData, exam_id: e.target.value })}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-fg focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                required
              >
                <option value="">Select Exam</option>
                {options?.exams.map((exam) => (
                  <option key={exam.id} value={exam.id}>
                    {exam.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Roll Number */}
            <div className="space-y-2">
              <label className="text-sm font-medium text-fg">Roll Number / Student ID</label>
              <input
                type="text"
                value={formData.roll_no}
                onChange={(e) => setFormData({ ...formData, roll_no: e.target.value })}
                placeholder="Enter roll number"
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-fg placeholder:text-muted focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                required
              />
            </div>
          </div>

          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={isSearching}
              className="min-w-[120px]"
            >
              {isSearching ? (
                <>
                  <Loader2 size={16} className="mr-2 animate-spin" />
                  Searching...
                </>
              ) : (
                <>
                  <Search size={16} className="mr-2" />
                  Search Result
                </>
              )}
            </Button>
          </div>
        </form>

        {searchError && (
          <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
            <div className="flex items-start">
              <AlertCircle size={16} className="mr-2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="font-medium">Search Error</p>
                <p className="mt-1 text-red-700 dark:text-red-300">{searchError}</p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Results Display */}
      {searchResult && (
        <div className="space-y-6">
          {/* Student Information Card */}
          <div className="rounded-lg border border-border bg-bg p-6">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h3 className="text-xl font-semibold text-fg">Student Information</h3>
                <p className="mt-1 text-sm text-muted">
                  {searchResult.student.name} {searchResult.student.name_bn && `(${searchResult.student.name_bn})`}
                </p>
              </div>
              <Button
                onClick={handleDownloadPdf}
                disabled={isDownloading}
                variant="outline"
                size="sm"
              >
                {isDownloading ? (
                  <>
                    <Loader2 size={14} className="mr-2 animate-spin" />
                    Downloading...
                  </>
                ) : (
                  <>
                    <Download size={14} className="mr-2" />
                    Download Marksheet
                  </>
                )}
              </Button>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <div>
                <p className="text-xs font-medium text-muted uppercase">Student ID</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.student.student_uid}</p>
              </div>
              <div>
                <p className="text-xs font-medium text-muted uppercase">Roll Number</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.student.roll_no}</p>
              </div>
              <div>
                <p className="text-xs font-medium text-muted uppercase">Class</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.student.class_name}</p>
              </div>
              <div>
                <p className="text-xs font-medium text-muted uppercase">Section</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.student.section_name}</p>
              </div>
              <div>
                <p className="text-xs font-medium text-muted uppercase">Shift</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.student.shift_name}</p>
              </div>
              <div>
                <p className="text-xs font-medium text-muted uppercase">Exam</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.exam.name}</p>
              </div>
              <div>
                <p className="text-xs font-medium text-muted uppercase">Academic Year</p>
                <p className="mt-1 text-sm font-medium text-fg">{searchResult.academic_year.name}</p>
              </div>
              {(searchResult.student.fathers_name || searchResult.student.mothers_name) && (
                <div className="md:col-span-2 lg:col-span-2">
                  <p className="text-xs font-medium text-muted uppercase">Parents</p>
                  <p className="mt-1 text-sm font-medium text-fg">
                    {searchResult.student.fathers_name && `Father: ${searchResult.student.fathers_name}`}
                    {searchResult.student.fathers_name && searchResult.student.mothers_name && ' | '}
                    {searchResult.student.mothers_name && `Mother: ${searchResult.student.mothers_name}`}
                  </p>
                </div>
              )}
            </div>

            {searchResult.summary && (
              <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                {/* GPA Badge */}
                <div className="rounded-lg border border-border bg-bg-subtle p-4">
                  <p className="text-xs font-medium text-muted uppercase">GPA</p>
                  <p className="mt-2 text-2xl font-bold text-fg">
                    {searchResult.summary.gpa != null ? Number(searchResult.summary.gpa).toFixed(2) : '—'}
                  </p>
                </div>

                {/* Pass/Fail Status */}
                <div className={cn(
                  "rounded-lg border p-4",
                  searchResult.summary.is_pass
                    ? "border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20"
                    : "border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20"
                )}>
                  <div className="flex items-center">
                    {searchResult.summary.is_pass ? (
                      <CheckCircle size={20} className="mr-2 text-green-600 dark:text-green-400" />
                    ) : (
                      <XCircle size={20} className="mr-2 text-red-600 dark:text-red-400" />
                    )}
                    <div>
                      <p className="text-xs font-medium text-muted uppercase">Status</p>
                      <p className="mt-1 text-sm font-bold text-fg">
                        {searchResult.summary.is_pass ? 'Passed' : 'Failed'}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Class Position */}
                {searchResult.summary.class_position && (
                  <div className="rounded-lg border border-border bg-bg-subtle p-4">
                    <p className="text-xs font-medium text-muted uppercase">Class Position</p>
                    <p className="mt-2 text-2xl font-bold text-fg">#{searchResult.summary.class_position}</p>
                  </div>
                )}

                {/* Section Position */}
                {searchResult.summary.section_position && (
                  <div className="rounded-lg border border-border bg-bg-subtle p-4">
                    <p className="text-xs font-medium text-muted uppercase">Section Position</p>
                    <p className="mt-2 text-2xl font-bold text-fg">#{searchResult.summary.section_position}</p>
                  </div>
                )}

                {/* Total Marks */}
                <div className="rounded-lg border border-border bg-bg-subtle p-4">
                  <p className="text-xs font-medium text-muted uppercase">Total Obtained</p>
                  <p className="mt-2 text-lg font-bold text-fg">
                    {searchResult.summary.total_obtained} / {searchResult.summary.total_marks}
                  </p>
                </div>
              </div>
            )}
          </div>

          {/* Subject Marks Table */}
          <div className="rounded-lg border border-border bg-bg">
            <div className="border-b border-border p-4">
              <h3 className="text-lg font-semibold text-fg">Subject-wise Marks</h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-border bg-bg-subtle">
                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-muted">Subject</th>
                    <th className="px-4 py-3 text-center text-xs font-medium uppercase text-muted">Total Marks</th>
                    <th className="px-4 py-3 text-center text-xs font-medium uppercase text-muted">Obtained</th>
                    <th className="px-4 py-3 text-center text-xs font-medium uppercase text-muted">Grade</th>
                    <th className="px-4 py-3 text-center text-xs font-medium uppercase text-muted">Grade Point</th>
                    <th className="px-4 py-3 text-center text-xs font-medium uppercase text-muted">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {searchResult.subjects.map((subject, index) => (
                    <tr key={index} className="hover:bg-bg-subtle">
                      <td className="px-4 py-3">
                        <div>
                          <p className="font-medium text-fg">{subject.subject_name}</p>
                          <p className="text-xs text-muted">{subject.subject_code}</p>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-center text-sm text-fg">{subject.total_marks}</td>
                      <td className="px-4 py-3 text-center text-sm font-medium text-fg">
                        {subject.is_absent ? (
                          <span className="text-muted">Absent</span>
                        ) : (
                          subject.obtained_marks
                        )}
                      </td>
                      <td className="px-4 py-3 text-center text-sm text-fg">{subject.grade}</td>
                      <td className="px-4 py-3 text-center text-sm text-fg">
                        {subject.grade_point != null ? Number(subject.grade_point).toFixed(2) : '—'}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <span className={cn(
                          "inline-flex rounded-full px-2 py-1 text-xs font-medium",
                          subject.is_pass
                            ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300"
                            : "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300"
                        )}>
                          {subject.is_pass ? 'Pass' : 'Fail'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}