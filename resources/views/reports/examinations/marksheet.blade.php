@extends('reports.layout')

@section('content')
    @foreach ($data['rows'] as $row)
        <table style="margin-bottom: 16px;">
            <tr>
                <th colspan="3" class="text-center">
                    {{ $row['name'] }} &mdash; Total: {{ $row['total_obtained'] }}/{{ $row['total_marks'] }}
                    @if ($row['gpa'] !== null) &mdash; GPA {{ $row['gpa'] }} @endif
                    &mdash; {{ $row['is_pass'] ? 'Pass' : 'Fail' }}
                </th>
            </tr>
            <tr>
                <th>Subject</th>
                <th>Obtained</th>
                <th>Grade</th>
            </tr>
            @foreach ($row['subjects'] as $s)
                <tr>
                    <td>{{ $s['subject_name'] }}</td>
                    <td>{{ $s['is_absent'] ? 'Absent' : $s['obtained_marks'].'/'.$s['total_marks'] }}</td>
                    <td>{{ $s['grade'] ?? '-' }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
@endsection
