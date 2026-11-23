@php
    $stale = $errors->has('version');
    $enabled = $stale ? $settings->delivery_enabled : old('delivery_enabled', $settings->delivery_enabled);
    $fee = $stale ? $settings->flat_fee_vnd : old('flat_fee_vnd', $settings->flat_fee_vnd);
    $version = $stale ? $settings->version : old('version', $settings->version);
    $selected = $stale ? $settings->eligible_districts : old('eligible_districts', $errors->any() ? [] : $settings->eligible_districts);
    $selected = is_array($selected) ? $selected : [];
@endphp

<x-layouts::admin title="Cấu hình giao hàng">
    <section class="max-w-3xl space-y-5">
        <h1 class="text-2xl font-semibold">Cấu hình giao hàng</h1>

        @if (session('status'))
            <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-4 text-sm">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul role="alert" class="space-y-1 rounded-lg border border-terrasse-danger/30 bg-terrasse-paper p-4 text-sm text-terrasse-danger">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.delivery-settings.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')
            <input type="hidden" name="version" value="{{ $version }}">

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select name="delivery_enabled" label="Giao hàng" required>
                    <flux:select.option value="0" :selected="! $enabled">Tắt</flux:select.option>
                    <flux:select.option value="1" :selected="(bool) $enabled">Bật</flux:select.option>
                </flux:select>
                <flux:input name="flat_fee_vnd" type="number" label="Phí giao cố định (VND)"
                    :value="is_scalar($fee) ? $fee : ''" min="0" :max="\App\Models\DeliverySetting::MAX_FEE_VND" step="1" required />
            </div>

            <fieldset class="space-y-2">
                <legend class="font-semibold">Quận/huyện hỗ trợ giao</legend>
                <p class="text-sm">Mở tỉnh/thành phố để chọn quận/huyện. Khi bật giao hàng, cần chọn ít nhất một vùng.</p>
                <div class="divide-y divide-terrasse-ink/15 rounded-xl border border-terrasse-ink/15 bg-terrasse-paper">
                    @foreach ($catalog->provinces() as $provinceCode => $province)
                        @php
                            $districts = $catalog->districts((string) $provinceCode);
                            $countSelected = count(array_filter(array_keys($districts), fn ($code) => in_array((string) $code, $selected, true)));
                        @endphp
                        @if ($districts !== [])
                            <details @if ($countSelected > 0) open @endif>
                                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">
                                    {{ $province }} @if ($countSelected > 0) · {{ $countSelected }} đã chọn @endif
                                </summary>
                                <div class="grid gap-2 px-4 pb-4 sm:grid-cols-2">
                                    @foreach ($districts as $code => $district)
                                        <label class="flex items-start gap-2 text-sm">
                                            <input type="checkbox" name="eligible_districts[]" value="{{ $code }}"
                                                @checked(in_array((string) $code, $selected, true)) class="mt-0.5 size-4 shrink-0 accent-accent">
                                            <span>{{ $district }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endforeach
                </div>
            </fieldset>

            <flux:button type="submit" variant="primary">Lưu cấu hình</flux:button>
        </form>
    </section>
</x-layouts::admin>
