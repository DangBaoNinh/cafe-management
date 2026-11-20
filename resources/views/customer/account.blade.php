<x-layouts::app title="Tài khoản">
    <section class="space-y-4">
        <h1 class="text-2xl font-semibold">Tài khoản của bạn</h1>
        <p>Xin chào, {{ auth()->user()->name }}.</p>
        <flux:button :href="route('profile.edit')" wire:navigate>Cập nhật hồ sơ</flux:button>
    </section>
</x-layouts::app>
