<x-layouts::admin title="Đối chiếu phiếu nhận">
    <section class="mx-auto max-w-3xl space-y-5">
        <flux:link :href="route('operations.pickups.index')">Về lịch nhận tại quán</flux:link>
        <h1 class="text-2xl font-semibold">Đối chiếu phiếu nhận</h1>
        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4">{{ session('status') }}</p>
        @endif
        @foreach ($errors->all() as $message)
            <p role="alert" class="text-sm text-terrasse-danger">{{ $message }}</p>
        @endforeach
        @include('operations.pickups.fulfillment')
    </section>
</x-layouts::admin>
