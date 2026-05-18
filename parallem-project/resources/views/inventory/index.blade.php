<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سجل حركات المخزن والمبيعات</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
</head>
<body class="bg-light p-5">
<div class="container bg-white p-4 rounded shadow-sm">
    <h2 class="mb-4"> تقرير معالجة المبيعات اليومية </h2>

    <table class="table table-striped table-bordered text-center">
        <thead class="table-dark">
        <tr>
            <th>رقم الحركة</th>
            <th>رقم المنتج</th>
            <th>الكمية السابقة</th>
            <th>الكمية الجديدة</th>
            <th>نوع العملية</th>
            <th>المسؤول</th>
            <th>وقت المعالجة</th>
        </tr>
        </thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td><span class="badge bg-primary">#{{ $log->product_id }}</span></td>
                <td>{{ $log->previous_quantity }}</td>
                <td>{{ $log->new_quantity }}</td>
                <td><small class="text-muted">{{ $log->operation_type }}</small></td>
                <td>{{ $log->created_by }}</td>
                <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="d-flex justify-content-center mt-4">
        {{ $logs->links() }}
    </div>
</div>
</body>
</html>
