<x-layouts::admin title="Order tại bàn">
    <x-reservations.order-warning :seat="$tableSession->seat" />
    @include('table-orders.pos-form')
</x-layouts::admin>
