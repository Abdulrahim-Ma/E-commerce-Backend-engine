<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>confirm your oreder</title>
</head>
<body>
<h1>Hi</h1>
<p>we recieved your order wait a second</p>
<p><strong>process num:</strong> #{{ $order->id }}</p>
<p><strong>total price</strong> {{ $order->total_price }} $</p>
<br>
</body>
</html>
