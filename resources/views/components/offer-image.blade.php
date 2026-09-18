@props(['offer', 'size' => 'size-24'])

{{--
    Food is sold visually. When a shop has uploaded a photograph we show it;
    when it has not, we show a tinted category glyph rather than a grey box,
    so a list of unphotographed stock still reads as designed rather than broken.
    The box is always reserved at a fixed size so nothing shifts as images load.
--}}
@if ($offer->image)
    <img src="{{ Storage::url($offer->image) }}"
         alt="{{ $offer->title }}"
         loading="lazy" decoding="async"
         class="{{ $size }} shrink-0 rounded-xl object-cover">
@else
    <div class="{{ $size }} {{ $offer->category->tint() }} flex shrink-0 items-center justify-center rounded-xl"
         role="img" aria-label="{{ $offer->category->label() }}">
        <x-icon :name="$offer->category->icon()" class="size-1/2" />
    </div>
@endif
