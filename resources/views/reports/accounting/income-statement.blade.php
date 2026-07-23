@extends('reports.layout')

@section('content')
    <table>
        <tr>
            <th colspan="3">Income</th>
        </tr>
        <tr>
            <th>Code</th>
            <th>Account</th>
            <th>Amount</th>
        </tr>
        @foreach ($data['income'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="text-center">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="2">Total Income</th>
            <th class="text-center">{{ number_format($data['total_income'], 2) }}</th>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="3">Expense</th>
        </tr>
        <tr>
            <th>Code</th>
            <th>Account</th>
            <th>Amount</th>
        </tr>
        @foreach ($data['expense'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="text-center">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="2">Total Expense</th>
            <th class="text-center">{{ number_format($data['total_expense'], 2) }}</th>
        </tr>
    </table>

    <table>
        <tr>
            <th>Net {{ $data['net'] >= 0 ? 'Surplus' : 'Deficit' }}</th>
            <th class="text-center">{{ number_format(abs($data['net']), 2) }}</th>
        </tr>
    </table>
@endsection
