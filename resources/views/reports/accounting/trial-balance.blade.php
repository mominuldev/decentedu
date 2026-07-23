@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th>Code</th>
            <th>Account</th>
            <th>Type</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Balance</th>
        </tr>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ ucfirst($row['type']) }}</td>
                <td class="text-center">{{ number_format($row['debit'], 2) }}</td>
                <td class="text-center">{{ number_format($row['credit'], 2) }}</td>
                <td class="text-center">{{ number_format($row['balance'], 2) }} {{ $row['balance_side'] === 'debit' ? 'Dr' : 'Cr' }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3">Total</th>
            <th class="text-center">{{ number_format($data['total_debit'], 2) }}</th>
            <th class="text-center">{{ number_format($data['total_credit'], 2) }}</th>
            <th></th>
        </tr>
    </table>
@endsection
