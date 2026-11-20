<x-layouts::admin title="Chỉnh sửa quyền truy cập">
    <section class="max-w-xl space-y-5">
        <flux:link :href="route('admin.accounts.index')">Về danh sách tài khoản</flux:link>
        <h1 class="text-2xl font-semibold">Chỉnh sửa quyền truy cập</h1>
        <div>
            <p class="font-medium">{{ $account->name }}</p>
            <p class="break-all text-sm">{{ $account->email }}</p>
        </div>

        <x-auth-session-status :status="session('status')" />
        <flux:error name="account" />

        <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <input type="hidden" name="previous_role" value="{{ $account->role->value }}">
            <input type="hidden" name="previous_is_active" value="{{ (int) $account->is_active }}">

            <flux:select name="role" label="Vai trò" required>
                @foreach (\App\Enums\UserRole::cases() as $role)
                    <flux:select.option :value="$role->value" :selected="$account->role === $role">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select name="is_active" label="Trạng thái" required>
                <flux:select.option value="1" :selected="$account->is_active">Đang hoạt động</flux:select.option>
                <flux:select.option value="0" :selected="! $account->is_active">Đã khóa</flux:select.option>
            </flux:select>

            <flux:button type="submit" variant="primary">Lưu quyền truy cập</flux:button>
        </form>
    </section>
</x-layouts::admin>
