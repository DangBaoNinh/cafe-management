@props(['product', 'detail' => false])

<div {{ $attributes->class(['overflow-hidden bg-coffee/5']) }}>
    @if ($product->image_path)
        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}"
            alt="{{ $detail ? $product->name : '' }}"
            @unless ($detail) loading="lazy" @endunless
            class="size-full object-cover">
    @else
        <div class="grid size-full place-items-center text-coffee/55" aria-hidden="true">
            <flux:icon.photo class="size-9" />
        </div>
    @endif
</div>
