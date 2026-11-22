@props(['href', 'caption' => null])

<a href="{{ $href }}" {{ $attributes->class(['brand french-brand']) }} aria-label="Toàn Café &amp; Jardin{{ $caption ? ' · '.$caption : '' }}">
    <span class="brand-word">Toàn</span>
    <span class="brand-caption">Café &amp;<br>Jardin @if($caption)<span class="french-brand-context">{{ $caption }}</span>@endif</span>
</a>
