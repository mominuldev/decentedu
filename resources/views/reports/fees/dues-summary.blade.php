@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Class</th>
            <th>Students With Dues</th>
            <th>Total Due</th>
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['class_label'] }}</td>
                <td class="text-center">{{ $row['students_with_dues'] }}</td>
                <td class="text-center">{{ number_format($row['total_due'], 2) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
