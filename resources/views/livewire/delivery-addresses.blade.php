<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Địa chỉ giao hàng" subheading="Lưu người nhận và địa chỉ để dùng khi đặt hàng.">
        <div class="space-y-7">
            <div class="space-y-3">
                @forelse ($addresses as $address)
                    <article wire:key="address-{{ $address->id }}" class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading>{{ $address->recipient_name }}</flux:heading>
                            @if ($address->is_default)
                                <flux:badge size="sm" color="green">Mặc định</flux:badge>
                            @endif
                        </div>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $address->phone }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $address->address_line }},
                            @if ($address->ward_code !== null)
                                {{ $catalog->wards($address->province_code, $address->district_code)[$address->ward_code] }},
                            @endif
                            {{ $catalog->districts($address->province_code)[$address->district_code] }},
                            {{ $provinces[$address->province_code] }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <flux:button type="button" size="sm" wire:click="edit({{ $address->id }})">Sửa</flux:button>
                            @unless ($address->is_default)
                                <flux:button type="button" size="sm" wire:click="setDefault({{ $address->id }})">Đặt mặc định</flux:button>
                            @endunless
                            <flux:button type="button" size="sm" variant="ghost" wire:click="delete({{ $address->id }})"
                                wire:confirm="Bạn có muốn xóa địa chỉ này?">Xóa</flux:button>
                        </div>
                    </article>
                @empty
                    <flux:text>Bạn chưa có địa chỉ giao hàng.</flux:text>
                @endforelse
            </div>

            <form wire:submit="save" class="space-y-4">
                <flux:heading size="lg">{{ $editingId === null ? 'Thêm địa chỉ' : 'Sửa địa chỉ' }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="recipientName" label="Tên người nhận" required maxlength="120" autocomplete="section-delivery shipping name" />
                    <flux:input wire:model="phone" label="Số điện thoại" type="tel" required autocomplete="section-delivery shipping tel" placeholder="0901234567" />
                </div>

                <flux:text>Chọn địa chỉ theo danh mục hành chính trước 01/07/2025.</flux:text>
                <flux:select wire:model.live="provinceCode" label="Tỉnh/thành phố" required>
                    <flux:select.option value="">Chọn tỉnh/thành phố</flux:select.option>
                    @foreach ($provinces as $code => $name)
                        <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:key="districts-{{ $provinceCode }}" wire:model.live="districtCode"
                    label="Quận/huyện" required :disabled="$districts === []">
                    <flux:select.option value="">Chọn quận/huyện</flux:select.option>
                    @foreach ($districts as $code => $name)
                        <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($wards !== [])
                    <flux:select wire:key="wards-{{ $districtCode }}" wire:model="wardCode" label="Phường/xã" required>
                        <flux:select.option value="">Chọn phường/xã</flux:select.option>
                        @foreach ($wards as $code => $name)
                            <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:error name="wardCode" />
                @endif

                <flux:input wire:model="addressLine" label="Địa chỉ chi tiết" required maxlength="255"
                    autocomplete="section-delivery shipping street-address" placeholder="Số nhà, đường, tòa nhà" />
                <flux:checkbox wire:model="isDefault" label="Đặt làm địa chỉ mặc định" />

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary">Lưu địa chỉ</flux:button>
                    @if ($editingId !== null)
                        <flux:button type="button" wire:click="cancelEdit">Hủy sửa</flux:button>
                    @endif
                </div>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
