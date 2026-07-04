@props([
    'initials',
    'size' => 'size-8',
    'text' => 'text-xs',
])

<div {{ $attributes->class(['flex shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700 font-medium text-zinc-600 dark:text-zinc-300', $size, $text]) }}>
    {{ $initials }}
</div>
