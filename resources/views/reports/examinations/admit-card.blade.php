@extends('reports.layout')

@section('content')
    @foreach ($data['students'] as $student)
        <table style="margin-bottom: 12px;">
            <tr>
                <th colspan="2" class="text-center">Admit Card</th>
            </tr>
            <tr>
                <td>Name</td>
                <td>{{ $student['name'] }}</td>
            </tr>
            <tr>
                <td>Roll</td>
                <td>{{ $student['roll'] }}</td>
            </tr>
        </table>
        <table>
            <tr>
                <th>Subject</th>
                <th>Date</th>
                <th>Time</th>
                <th>Room</th>
            </tr>
            @foreach ($data['routine'] as $r)
                <tr>
                    <td>{{ $r['subject_name'] }}</td>
                    <td>{{ $r['exam_date'] }}</td>
                    <td>{{ $r['start_time'] }}&ndash;{{ $r['end_time'] }}</td>
                    <td>{{ $r['room_no'] }}</td>
                </tr>
            @endforeach
        </table>
        @if ($data['instructions'])
            <p>
                {{ $data['instructions']->instruction1 }}
                {{ $data['instructions']->instruction2 }}
                {{ $data['instructions']->instruction3 }}
                {{ $data['instructions']->instruction4 }}
            </p>
        @endif
        <table class="signatures">
            <tr>
                @foreach ($data['signatures'] as $sig)
                    <td><span class="sig-line"></span><br>{{ $sig->person_name }}<br>{{ $sig->designation }}</td>
                @endforeach
            </tr>
        </table>
        @if (! $loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
@endsection
