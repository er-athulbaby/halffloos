@props(['name', 'label' => null])

@php
    $file = resource_path("svg/{$name}.svg");
    $body = file_exists($file)
        ? preg_replace('/^.*?<svg[^>]*>|<\/svg>\s*$/s', '', file_get_contents($file))
        : '';
@endphp

<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }}
     viewBox="0 0 256 256" fill="currentColor"
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
    {!! $body !!}
</svg>
