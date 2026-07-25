@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>UID</th>
            <th>Name</th>
            <th>Class</th>
            <th>Roll</th>
            <th>Father's Name</th>
            <th>Mobile</th>
            <th>Status</th>
        </tr>
        @foreach ($data['rows'] as $student)
            <tr>
                <td>{{ $student->student_uid }}</td>
                <td>{{ $student->name }}</td>
                <td>{{ $student->currentEnrollment?->classConfig?->label() ?? '' }}</td>
                <td>{{ $student->currentEnrollment?->roll ?? '' }}</td>
                <td>{{ $student->fathers_name }}</td>
                <td>{{ $student->mobile ?? '' }}</td>
                <td>{{ ucfirst($student->status) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
