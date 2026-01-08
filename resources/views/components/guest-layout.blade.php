{{-- resources/views/components/guest-layout.blade.php --}}
@props(['title' => null])

<x-layouts.guest :title="$title">
    {{ $slot }}
</x-layouts.guest>
