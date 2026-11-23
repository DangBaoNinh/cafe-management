@props(['title', 'number'])
<header class="receipt-header">
    <p class="receipt-brand">TOÀN CAFÉ &amp; JARDIN</p>
    <h1 class="receipt-title">{{ $title }}</h1>
    <p class="receipt-number">Số: <strong>{{ $number }}</strong></p>
</header>
