<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'XBRiyaz', sans-serif; direction: rtl; text-align: right; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; }
        .title { font-size: 24px; color: #333; }
        table { width: 100%; line-height: inherit; text-align: right; border-collapse: collapse; }
        table td { padding: 5px; vertical-align: top; }
        table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
    </style>
</head>
<body>
<div class="invoice-box">
    <table>
        <tr>
            <td class="title">bill</td>
            <td> bill id: #{{ $order->id }}<br>date: {{ $order->created_at->format('Y-m-d') }}</td>
        </tr>
    </table>
    <br>
    <table>
        <tr>
            <td><strong>customer:</strong> {{ $order->user->name }}</td>
            <td><strong>email:</strong> {{ $order->user->email }}</td>
        </tr>
    </table>
    <br>
    <table>
        <tr class="heading">
            <td>order detial</td>
            <td>total price</td>
        </tr>
        <tr>
            <td></td>
            <td>{{ $order->total_price }} $</td>
        </tr>
    </table>
</div>
</body>
</html>
