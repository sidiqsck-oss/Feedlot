@props(['judul'])

<div>
    <p class="px-2 pb-1 text-[0.65rem] font-bold uppercase tracking-wider text-ink-mute">{{ $judul }}</p>
    <div class="space-y-0.5">{{ $slot }}</div>
</div>
