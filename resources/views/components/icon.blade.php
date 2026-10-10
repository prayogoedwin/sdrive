@props(['name' => 'file', 'class' => 'size-5'])

@php
    $paths = [
        'folder' => 'M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v8A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-10Z',
        'drive' => 'M4 7.5 8 4h8l4 3.5V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7.5Z M4 8h16',
        'common' => 'M8 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z M16 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z M12 18.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z M9.5 10.2 11 14 M14.5 10.2 13 14',
        'users' => 'M16 19v-1a3 3 0 0 0-3-3H7a3 3 0 0 0-3 3v1 M10 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M20 19v-1a3 3 0 0 0-2.2-2.9 M16 6.1a3 3 0 0 1 0 5.8',
        'clock' => 'M12 8v4l2.5 2.5 M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
        'star' => 'm12 3 2.6 5.4 6 .9-4.3 4.2 1 5.9L12 16.8 6.7 19.4l1-5.9L3.4 9.3l6-.9L12 3Z',
        'trash' => 'M4 7h16 M9 7V5h6v2 M7 7l1 13h8l1-13',
        'share' => 'M15 8a3 3 0 1 0-2.8-4 M9 14a3 3 0 1 0 0 2 M15 16a3 3 0 1 0 2.8 4 M8.6 13.2l6.8 3.6 M15.4 7.2 8.6 10.8',
        'bell' => 'M6 16V11a6 6 0 1 1 12 0v5l1.5 2H4.5L6 16Z M10 19a2 2 0 0 0 4 0',
        'search' => 'm20 20-4.2-4.2 M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z',
        'grid' => 'M4 4h7v7H4z M13 4h7v7h-7z M4 13h7v7H4z M13 13h7v7h-7z',
        'list' => 'M8 6h12 M8 12h12 M8 18h12 M4 6h.01 M4 12h.01 M4 18h.01',
        'plus' => 'M12 5v14 M5 12h14',
        'upload' => 'M12 16V5 M7 9l5-5 5 5 M5 19h14',
        'download' => 'M12 4v11 M7 11l5 5 5-5 M5 20h14',
        'menu' => 'M4 7h16 M4 12h16 M4 17h16',
        'x' => 'M6 6l12 12 M18 6 6 18',
        'chevron' => 'm9 6 6 6-6 6',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 11v5 M12 8h.01',
        'logout' => 'M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2 M4 12h11 M11 8l4 4-4 4',
        'restore' => 'M4 12a8 8 0 1 0 2.3-5.6 M4 4v5h5',
        'check' => 'm5 12 5 5L20 7',
        'image' => 'M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Z M8 10.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z M4 16l4.5-4 3 3L15 11l5 5',
        'pdf' => 'M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z M14 3v6h6',
        'video' => 'M4 7h11v10H4z M15 11l5-3v8l-5-3z',
        'audio' => 'M9 18V6l10-2v12 M9 18a2.5 2.5 0 1 1-2.5-2.5A2.5 2.5 0 0 1 9 18Z M19 16a2.5 2.5 0 1 1-2.5-2.5A2.5 2.5 0 0 1 19 16Z',
        'archive' => 'M4 7h16v3H4z M6 10v9h12v-9 M10 14h4',
        'sheet' => 'M5 4h14v16H5z M5 9h14 M5 14h14 M10 4v16',
        'doc' => 'M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z M9 13h6 M9 17h4',
        'text' => 'M7 4h10 M12 4v16 M8 20h8',
        'file' => 'M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z M14 3v6h6',
    ];
    $d = $paths[$name] ?? $paths['file'];
    $filled = in_array($name, ['folder', 'star'], true) && $attributes->has('solid');
@endphp

<svg {{ $attributes->merge(['class' => $class])->except('solid') }} viewBox="0 0 24 24" fill="{{ $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if ($filled && $name === 'folder')
        <path d="{{ $paths['folder'] }}" fill="currentColor" stroke="none"/>
    @elseif ($filled && $name === 'star')
        <path d="{{ $paths['star'] }}" fill="currentColor" stroke="none"/>
    @else
        <path d="{{ $d }}"/>
    @endif
</svg>
