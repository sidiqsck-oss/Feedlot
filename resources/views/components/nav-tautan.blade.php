@props(['href', 'aktif' => false])

<a
    href="{{ $href }}"
    @class([
        'block rounded-md px-2 py-1.5 text-sm',
        'bg-accent-soft font-semibold text-accent' => $aktif,
        'text-ink-soft hover:bg-ground' => ! $aktif,
    ])
>{{ $slot }}</a>
