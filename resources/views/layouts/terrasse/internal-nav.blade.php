<nav aria-label="Điều hướng nội bộ" class="french-internal-nav space-y-5">
    @foreach ($sections as $label => $links)
        @continue($links === [])
        <div>
            <p class="sidebar-label">{{ $label }}</p>
            <ul class="space-y-1 text-sm">
                @foreach ($links as [$route, $pattern, $text])
                    <li>
                        <a href="{{ route($route) }}"
                            @if (request()->routeIs($pattern)) aria-current="{{ request()->routeIs($route) ? 'page' : 'true' }}" @endif
                            class="french-nav-link">{{ $text }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <div class="border-t border-terrasse-ink/15 pt-3 text-sm">
        <a href="{{ route('home') }}" class="block rounded px-3 py-2">Về trang chủ</a>
        <a href="{{ route('dashboard') }}" class="block rounded px-3 py-2">Tài khoản</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full cursor-pointer rounded px-3 py-2 text-start">Đăng xuất</button>
        </form>
    </div>
</nav>
