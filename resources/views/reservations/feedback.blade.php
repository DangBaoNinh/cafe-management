@if (session('status'))
    <p role="status" class="rounded-xl border border-terrasse-ink/15 bg-terrasse-paper p-3">{{ session('status') }}</p>
@endif
@if ($errors->any())
    <ul role="alert" class="space-y-1 rounded-xl border border-terrasse-danger/30 bg-terrasse-danger-surface p-3 text-terrasse-danger">
        @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
