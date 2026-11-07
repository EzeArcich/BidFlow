@props(['name' => 'arrow'])
@php($paths = [
'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/>
<rect x="14" y="3" width="7" height="7" rx="1.5"/>
<rect x="3" y="14" width="7" height="7" rx="1.5"/>
<rect x="14" y="14" width="7" height="7" rx="1.5"/>',
'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
<path d="M14 3v6h6M8 13h8M8 17h5"/>',
'users' => '<circle cx="9" cy="8" r="3"/>
<path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5"/>',
'plus' => '<path d="M12 5v14M5 12h14"/>',
'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
'check' => '<path d="m5 12 4 4L19 6"/>',
'search' => '<circle cx="10.5" cy="10.5" r="6.5"/>
<path d="m16 16 5 5"/>',
'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
'logout' => '<path d="M9 4H5v16h4M14 8l4 4-4 4M8 12h10"/>',
'building' => '<path d="M4 21V3h12v18M16 10h4v11M8 7h4M8 11h4M8 15h4M2 21h20"/>',
'clock' => '<circle cx="12" cy="12" r="9"/>
<path d="M12 7v5l3 2"/>',
'edit' => '<path d="m15 4 5 5M4 20l5-1L21 7a2 2 0 0 0-5-5L4 14z"/>',
])
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['arrow'] !!}</svg>
