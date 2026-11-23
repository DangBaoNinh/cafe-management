@props(['date'])
<time datetime="{{ $date->toIso8601String() }}">{{ $date->copy()->setTimezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}</time>
