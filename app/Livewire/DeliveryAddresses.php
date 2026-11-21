<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\DeliveryAddressBook;
use App\Services\VietnamAddressCatalog;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::settings')]
#[Title('Địa chỉ giao hàng')]
class DeliveryAddresses extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $recipientName = '';

    public string $phone = '';

    public string $provinceCode = '';

    public string $districtCode = '';

    public string $wardCode = '';

    public string $addressLine = '';

    public bool $isDefault = false;

    protected VietnamAddressCatalog $catalog;

    public function boot(VietnamAddressCatalog $catalog): void
    {
        $this->catalog = $catalog;
    }

    public function updatedProvinceCode(): void
    {
        $this->reset('districtCode', 'wardCode');
        $this->resetValidation(['provinceCode', 'districtCode', 'wardCode']);
    }

    public function updatedDistrictCode(): void
    {
        $this->reset('wardCode');
        $this->resetValidation(['districtCode', 'wardCode']);
    }

    public function edit(int $id): void
    {
        $address = $this->customer()->addresses()->findOrFail($id);
        $this->resetValidation();

        $this->fill([
            'editingId' => $address->id,
            'recipientName' => $address->recipient_name,
            'phone' => $address->phone,
            'provinceCode' => $address->province_code,
            'districtCode' => $address->district_code,
            'wardCode' => $address->ward_code ?? '',
            'addressLine' => $address->address_line,
            'isDefault' => $address->is_default,
        ]);
    }

    public function save(DeliveryAddressBook $addressBook): void
    {
        $customer = $this->customer();
        $this->recipientName = trim($this->recipientName);
        $this->phone = trim($this->phone);
        $this->addressLine = trim($this->addressLine);

        $wards = $this->catalog->wards($this->provinceCode, $this->districtCode);

        $this->validate([
            'recipientName' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/\A(?:0|\+84)[1-9][0-9]{8,9}\z/'],
            'provinceCode' => ['required', Rule::in(array_keys($this->catalog->provinces()))],
            'districtCode' => ['required', Rule::in(array_keys($this->catalog->districts($this->provinceCode)))],
            'wardCode' => [$wards === [] ? 'nullable' : 'required', Rule::in(array_keys($wards))],
            'addressLine' => ['required', 'string', 'max:255'],
            'isDefault' => ['boolean'],
        ], [
            'phone.regex' => 'Số điện thoại phải bắt đầu bằng 0 hoặc +84 và có định dạng hợp lệ.',
        ], [
            'recipientName' => 'tên người nhận',
            'phone' => 'số điện thoại',
            'provinceCode' => 'tỉnh/thành phố',
            'districtCode' => 'quận/huyện',
            'wardCode' => 'phường/xã',
            'addressLine' => 'địa chỉ chi tiết',
            'isDefault' => 'địa chỉ mặc định',
        ]);

        $addressBook->save($customer, [
            'recipient_name' => $this->recipientName,
            'phone' => $this->phone,
            'province_code' => $this->provinceCode,
            'district_code' => $this->districtCode,
            'ward_code' => $this->wardCode === '' ? null : $this->wardCode,
            'address_line' => $this->addressLine,
            'is_default' => $this->isDefault,
        ], $this->editingId);

        $this->cancelEdit();
        Flux::toast(variant: 'success', text: 'Đã lưu địa chỉ giao hàng.');
    }

    public function setDefault(int $id, DeliveryAddressBook $addressBook): void
    {
        $addressBook->save($this->customer(), ['is_default' => true], $id);

        if ($this->editingId !== null) {
            $this->isDefault = $this->editingId === $id;
        }

        Flux::toast(variant: 'success', text: 'Đã đặt địa chỉ mặc định.');
    }

    public function delete(int $id, DeliveryAddressBook $addressBook): void
    {
        $addressBook->delete($this->customer(), $id);

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        Flux::toast(variant: 'success', text: 'Đã xóa địa chỉ giao hàng.');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'recipientName', 'phone', 'provinceCode', 'districtCode', 'wardCode', 'addressLine', 'isDefault');
        $this->resetValidation();
    }

    private function customer(): User
    {
        Gate::authorize('manage-delivery-addresses');

        /** @var User $customer */
        $customer = auth()->user();

        return $customer;
    }

    public function render(): View
    {
        return view('livewire.delivery-addresses', [
            'addresses' => $this->customer()->addresses()->orderByDesc('is_default')->latest('id')->get(),
            'provinces' => $this->catalog->provinces(),
            'districts' => $this->catalog->districts($this->provinceCode),
            'wards' => $this->catalog->wards($this->provinceCode, $this->districtCode),
            'catalog' => $this->catalog,
        ]);
    }
}
