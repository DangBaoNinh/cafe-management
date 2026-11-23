@props(['amount'])
{{ preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', (string) $amount) }} ₫
