<x-layouts::admin title="Quản lý tài khoản">
    <section class="space-y-6">
        <h1 class="text-2xl font-semibold">Quản lý tài khoản</h1>

        <form action="{{ route('admin.accounts.index') }}" method="GET" class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]">
            <flux:input name="search" label="Tên hoặc email" :value="$search" />
            <flux:select name="role" label="Vai trò">
                <flux:select.option value="">Tất cả vai trò</flux:select.option>
                @foreach (\App\Enums\UserRole::cases() as $option)
                    <flux:select.option :value="$option->value" :selected="$role === $option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select name="status" label="Trạng thái">
                <flux:select.option value="">Tất cả trạng thái</flux:select.option>
                <flux:select.option value="active" :selected="$status === 'active'">Đang hoạt động</flux:select.option>
                <flux:select.option value="locked" :selected="$status === 'locked'">Đã khóa</flux:select.option>
            </flux:select>
            <flux:button type="submit" variant="primary">Lọc tài khoản</flux:button>
        </form>

        <div class="relative overflow-x-auto rounded-lg border border-terrasse-ink/15 bg-terrasse-paper">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-terrasse-ink/15">
                    <tr>
                        <th scope="col" class="p-4">Tài khoản</th>
                        <th scope="col" class="p-4">Vai trò</th>
                        <th scope="col" class="p-4">Trạng thái</th>
                        <th scope="col" class="p-4"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-terrasse-ink/15">
                    @forelse ($accounts as $account)
                        <tr>
                            <td class="p-4"><div class="font-medium">{{ $account->name }}</div>{{ $account->email }}</td>
                            <td class="p-4">{{ $account->role->label() }}</td>
                            <td class="p-4">{{ $account->is_active ? 'Đang hoạt động' : 'Đã khóa' }}</td>
                            <td class="p-4 text-right">
                                @if (auth()->id() === $account->id)
                                    <span>Tài khoản của bạn</span>
                                @else
                                    <flux:button size="sm" :href="route('admin.accounts.edit', $account)">Chỉnh sửa / khóa tài khoản<span class="sr-only"> {{ $account->email }}</span></flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4">Không tìm thấy tài khoản.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $accounts->links() }}
    </section>
</x-layouts::admin>
