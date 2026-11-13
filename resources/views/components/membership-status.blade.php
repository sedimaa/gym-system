@props(['status'])
<span {{ $attributes->class([$status->badgeClass()]) }}>{{ $status->label() }}</span>
