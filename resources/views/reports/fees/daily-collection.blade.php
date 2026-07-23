@extends('reports.layout')

@section('content')
    <p>Period: {{ $data['from'] }} to {{ $data['to'] }}</p>
    <table>
        <tr>
            <th>Fee Head</th>
            <th>Amount</th>
        </tr>
        @foreach ($data['by_head'] as $row)
            <tr>
                <td>{{ $row['fee_head_name'] }}</td>
                <td class="text-center">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total ({{ $data['receipts_count'] }} receipts)</th>
            <th class="text-center">{{ number_format($data['total_collected'], 2) }}</th>
        </tr>
    </table>
@endsection
