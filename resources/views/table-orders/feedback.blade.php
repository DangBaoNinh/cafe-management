@if (session('status'))
    <p role="status" class="rounded-lg border border-terrasse-ink/15 bg-terrasse-paper p-3">{{ session('status') }}</p>
@endif
@if ($errors->any())
    <ul role="alert" class="list-disc space-y-1 rounded-lg border border-terrasse-danger/30 bg-terrasse-danger-surface py-3 pe-3 ps-8 text-terrasse-danger">
        @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
