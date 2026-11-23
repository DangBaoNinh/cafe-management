<div x-data class="min-w-0">
    <x-pages::settings.layout heading="Địa chỉ giao hàng" subheading="Lưu người nhận và địa chỉ để dùng khi đặt hàng.">
        <div class="grid gap-7 xl:grid-cols-2">
            <section aria-labelledby="saved-addresses-heading" class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 id="saved-addresses-heading" class="terrasse-display text-2xl font-semibold">Địa chỉ đã lưu</h3>
                    <a href="#delivery-address-form" class="terrasse-settings-jump text-sm font-medium underline underline-offset-4 xl:hidden">
                        Đến biểu mẫu
                    </a>
                </div>
                @forelse ($addresses as $address)
                    <article wire:key="address-{{ $address->id }}" class="terrasse-address-card space-y-2 rounded-md p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-semibold wrap-anywhere">{{ $address->recipient_name }}</h4>
                            @if ($address->is_default)
                                <flux:badge size="sm" color="green">Mặc định</flux:badge>
                            @endif
                        </div>
                        <p class="terrasse-muted text-sm">{{ $address->phone }}</p>
                        <p class="terrasse-muted text-sm leading-relaxed wrap-anywhere">
                            {{ $address->address_line }},
                            @if ($address->ward_code !== null)
                                {{ $catalog->wards($address->province_code, $address->district_code)[$address->ward_code] }},
                            @endif
                            {{ $catalog->districts($address->province_code)[$address->district_code] }},
                            {{ $provinces[$address->province_code] }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <flux:button type="button" size="sm" wire:loading.attr="disabled" wire:target="edit({{ $address->id }})"
                                x-on:click="await $wire.edit({{ $address->id }}); $refs.addressForm.focus()">
                                Sửa
                            </flux:button>
                            @unless ($address->is_default)
                                <flux:button type="button" size="sm" wire:click="setDefault({{ $address->id }})">Đặt mặc định</flux:button>
                            @endunless
                            <flux:button type="button" size="sm" variant="ghost" wire:click="delete({{ $address->id }})"
                                wire:confirm="Bạn có muốn xóa địa chỉ này?">Xóa</flux:button>
                        </div>
                    </article>
                @empty
                    <p class="terrasse-address-empty terrasse-muted rounded-md p-4 text-sm">Bạn chưa có địa chỉ giao hàng.</p>
                @endforelse
            </section>

            <form id="delivery-address-form" x-ref="addressForm" tabindex="-1" aria-labelledby="delivery-address-form-heading"
                wire:submit="save" class="terrasse-address-form min-w-0 space-y-4">
                <h3 id="delivery-address-form-heading" class="terrasse-display text-2xl font-semibold">
                    {{ $editingId === null ? 'Thêm địa chỉ' : 'Sửa địa chỉ' }}
                </h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="recipientName" label="Tên người nhận" required maxlength="120" autocomplete="section-delivery shipping name" />
                    <flux:input wire:model="phone" label="Số điện thoại" type="tel" required autocomplete="section-delivery shipping tel" placeholder="0901234567" />
                </div>

                <p class="terrasse-muted text-sm">Chọn địa chỉ theo danh mục hành chính trước 01/07/2025.</p>
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
</div>
