/**
 * Placeholder dashboard data. Shapes mirror what the `/api/v1/dashboard` endpoint
 * will return once the backend module is built; values are representative sample data
 * (branches & counts echo the analysed demo instance).
 */

export const branches = [
    'Demo IT School',
    'Demo College',
    'Demo School',
    'Horipur Girls High School',
    'Masud-UL Haque Institute',
];

export const stats = [
    { key: 'students', label: 'Total Students', value: 1284, delta: +4.2, spark: [1180, 1195, 1210, 1220, 1240, 1258, 1284] },
    { key: 'present', label: 'Present Today', value: 1173, sub: '91.4% attendance', delta: +1.1, spark: [88, 90, 87, 91, 92, 90, 91] },
    { key: 'collection', label: "Today's Collection", value: 184500, money: true, delta: +12.6, spark: [120, 140, 128, 156, 168, 172, 184] },
    { key: 'dues', label: 'Outstanding Dues', value: 962300, money: true, delta: -3.4, tone: 'warning' as const, spark: [1040, 1020, 1005, 998, 985, 972, 962] },
];

export const attendanceTrend = [
    { d: 'Jul 09', present: 89, absent: 11 },
    { d: 'Jul 10', present: 92, absent: 8 },
    { d: 'Jul 11', present: 90, absent: 10 },
    { d: 'Jul 13', present: 94, absent: 6 },
    { d: 'Jul 14', present: 91, absent: 9 },
    { d: 'Jul 15', present: 88, absent: 12 },
    { d: 'Jul 16', present: 93, absent: 7 },
    { d: 'Jul 17', present: 95, absent: 5 },
    { d: 'Jul 20', present: 90, absent: 10 },
    { d: 'Jul 21', present: 92, absent: 8 },
    { d: 'Jul 22', present: 91, absent: 9 },
    { d: 'Jul 23', present: 91, absent: 9 },
];

export const collectionByMonth = [
    { m: 'Feb', amount: 3120000 },
    { m: 'Mar', amount: 3480000 },
    { m: 'Apr', amount: 2960000 },
    { m: 'May', amount: 3720000 },
    { m: 'Jun', amount: 3510000 },
    { m: 'Jul', amount: 2890000 },
];

export const enrollmentByClass = [
    { c: 'Six', boys: 118, girls: 104 },
    { c: 'Seven', boys: 126, girls: 111 },
    { c: 'Eight', boys: 109, girls: 121 },
    { c: 'Nine', boys: 132, girls: 98 },
    { c: 'Ten', boys: 121, girls: 114 },
];

export const notices = [
    { id: 1, title: 'Half-yearly examination routine published', tag: 'Exam', tone: 'brand' as const, when: '2h ago' },
    { id: 2, title: 'July tuition fee — last date 28 July', tag: 'Fees', tone: 'warning' as const, when: '5h ago' },
    { id: 3, title: 'Parent–teacher meeting for Class Nine', tag: 'Notice', tone: 'sky' as const, when: 'Yesterday' },
    { id: 4, title: 'National holiday — school closed Friday', tag: 'Holiday', tone: 'neutral' as const, when: '2d ago' },
];

export const upcomingExams = [
    { id: 1, name: 'Half Yearly — Class Ten', date: '27 Jul', subject: 'Mathematics', room: '301' },
    { id: 2, name: 'Half Yearly — Class Nine', date: '28 Jul', subject: 'Physics', room: '204' },
    { id: 3, name: 'Monthly — Class Eight', date: '30 Jul', subject: 'English 1st', room: '112' },
];

export const recentAdmissions = [
    { id: 1, name: 'Ayesha Siddika', cls: 'Six · A', roll: '2026-041', status: 'Admitted' as const },
    { id: 2, name: 'Rahim Uddin', cls: 'Seven · B', roll: '2026-042', status: 'Admitted' as const },
    { id: 3, name: 'Tanvir Ahmed', cls: 'Nine · A', roll: '—', status: 'Pending' as const },
    { id: 4, name: 'Nusrat Jahan', cls: 'Eight · A', roll: '2026-043', status: 'Admitted' as const },
    { id: 5, name: 'Sabbir Hossain', cls: 'Ten · C', roll: '—', status: 'Waiting' as const },
];
