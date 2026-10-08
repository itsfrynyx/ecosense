<?php
/**
 * Inline stroke icon set: 24x24, currentColor, 1.5px stroke.
 * icon('thermometer', 'is-large')
 */

function icon($name, $class = '')
{
    static $icons = [
        'logo'            => '<circle cx="12" cy="12" r="9.2"/><circle cx="12" cy="12" r="4.6"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/><path d="M12 12V4.6"/>',
        'sensor-rings'    => '<circle cx="12" cy="12" r="2.4"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 16.2a6 6 0 0 0 0-8.4"/><path d="M4.9 4.9a10 10 0 0 0 0 14.2M19.1 19.1a10 10 0 0 0 0-14.2"/>',
        'sun'             => '<circle cx="12" cy="12" r="4"/><path d="M12 2.6v2.1M12 19.3v2.1M4.2 4.2l1.5 1.5M18.3 18.3l1.5 1.5M2.6 12h2.1M19.3 12h2.1M4.2 19.8l1.5-1.5M18.3 5.7l1.5-1.5"/>',
        'moon'            => '<path d="M20 14.2A8.2 8.2 0 0 1 9.8 4a8.4 8.4 0 1 0 10.2 10.2z"/>',
        'cloud'           => '<path d="M7.5 18.5h9.2a3.6 3.6 0 0 0 .3-7.2 5.4 5.4 0 0 0-10.3-1 3.7 3.7 0 0 0 .8 8.2z"/>',
        'rain'            => '<path d="M7.6 14.6h9a3.4 3.4 0 0 0 .3-6.8 5.2 5.2 0 0 0-9.8-1 3.5 3.5 0 0 0 .5 7.8z"/><path d="M8.6 17.4l-.9 2.4M12.4 17.4l-.9 2.4M16.2 17.4l-.9 2.4"/>',
        'droplet'         => '<path d="M12 3.2c2.6 3.2 5.4 6 5.4 9.2A5.4 5.4 0 0 1 12 17.8a5.4 5.4 0 0 1-5.4-5.4c0-3.2 2.8-6 5.4-9.2z"/>',
        'thermometer'     => '<path d="M13.9 13.6V5.2a2.4 2.4 0 1 0-4.8 0v8.4a4.4 4.4 0 1 0 4.8 0z"/><path d="M11.5 8.4v6.4"/>',
        'gauge'           => '<path d="M3.9 17.6a9.4 9.4 0 1 1 16.2 0"/><path d="M12 17.4l3.9-4.6"/><circle cx="12" cy="17.6" r="1.4"/><path d="M5.6 11.2h1.8M16.6 11.2h1.8"/>',
        'chip'            => '<rect x="7.4" y="7.4" width="9.2" height="9.2" rx="1.4"/><rect x="10.6" y="10.6" width="2.8" height="2.8" rx=".6"/><path d="M10.4 7.4V4.6M13.6 7.4V4.6M10.4 19.4v-2.8M13.6 19.4v-2.8M7.4 10.4H4.6M7.4 13.6H4.6M19.4 10.4h-2.8M19.4 13.6h-2.8"/>',
        'wifi'            => '<path d="M3.4 9.3a13 13 0 0 1 17.2 0"/><path d="M6.5 12.6a8.4 8.4 0 0 1 11 0"/><path d="M9.6 15.9a3.9 3.9 0 0 1 4.8 0"/><path d="M12 19.3h.01"/>',
        'bell'            => '<path d="M6.6 10.4a5.4 5.4 0 0 1 10.8 0c0 4 1.6 5.4 1.6 5.4H5s1.6-1.4 1.6-5.4z"/><path d="M10.2 18.4a2 2 0 0 0 3.6 0"/>',
        'dashboard'       => '<rect x="3.6" y="3.6" width="7" height="7" rx="1.3"/><rect x="13.4" y="3.6" width="7" height="4.4" rx="1.3"/><rect x="3.6" y="13.4" width="7" height="7" rx="1.3"/><rect x="13.4" y="11" width="7" height="9.4" rx="1.3"/>',
        'devices'         => '<rect x="2.8" y="5" width="18.4" height="11" rx="2"/><path d="M8 19.6h8M12 16v3.6"/><path d="M6.8 12.6h2.4l1.1-2.8 1.6 4.4 1.1-1.6h2.2"/>',
        'history'         => '<path d="M3.6 12a8.4 8.4 0 1 0 2.6-6.1"/><path d="M3.4 4.6v4.2h4.2"/><path d="M12 7.8V12l3 1.8"/>',
        'alert'           => '<circle cx="12" cy="12" r="8.4"/><path d="M12 8v4.6M12 15.9h.01"/>',
        'alert-triangle'  => '<path d="M10.3 4.2 2.9 17.4a1.6 1.6 0 0 0 1.4 2.4h15.4a1.6 1.6 0 0 0 1.4-2.4L13.7 4.2a1.6 1.6 0 0 0-2.8 0z"/><path d="M12 9.4v4M12 16.5h.01"/>',
        'settings'        => '<circle cx="12" cy="12" r="3"/><path d="M19.3 14.4a1.4 1.4 0 0 0 .3 1.6l.1.1a1.7 1.7 0 1 1-2.4 2.4l-.1-.1a1.4 1.4 0 0 0-1.6-.3 1.4 1.4 0 0 0-.9 1.3v.2a1.7 1.7 0 1 1-3.4 0v-.1a1.4 1.4 0 0 0-1-1.3 1.4 1.4 0 0 0-1.5.3l-.1.1a1.7 1.7 0 1 1-2.4-2.4l.1-.1a1.4 1.4 0 0 0 .3-1.6 1.4 1.4 0 0 0-1.3-.9h-.2a1.7 1.7 0 1 1 0-3.4h.1a1.4 1.4 0 0 0 1.3-1 1.4 1.4 0 0 0-.3-1.5l-.1-.1a1.7 1.7 0 1 1 2.4-2.4l.1.1a1.4 1.4 0 0 0 1.6.3h.1a1.4 1.4 0 0 0 .9-1.3v-.2a1.7 1.7 0 1 1 3.4 0v.1a1.4 1.4 0 0 0 .9 1.3 1.4 1.4 0 0 0 1.6-.3l.1-.1a1.7 1.7 0 1 1 2.4 2.4l-.1.1a1.4 1.4 0 0 0-.3 1.6v.1a1.4 1.4 0 0 0 1.3.9h.2a1.7 1.7 0 1 1 0 3.4h-.1a1.4 1.4 0 0 0-1.3.9z"/>',
        'logout'          => '<path d="M9.6 20.4H5.4a1.6 1.6 0 0 1-1.6-1.6V5.2a1.6 1.6 0 0 1 1.6-1.6h4.2"/><path d="M15.4 16.4l4.4-4.4-4.4-4.4M19.8 12H9.2"/>',
        'login'           => '<path d="M14.4 20.4h4.2a1.6 1.6 0 0 0 1.6-1.6V5.2a1.6 1.6 0 0 0-1.6-1.6h-4.2"/><path d="M8.6 16.4 4.2 12l4.4-4.4M4.2 12h10.6"/>',
        'check'           => '<path d="M4.8 12.6l4.6 4.6L19.2 7.4"/>',
        'check-circle'    => '<circle cx="12" cy="12" r="8.4"/><path d="M8.2 12.2l2.7 2.7 5-5.2"/>',
        'x-circle'        => '<circle cx="12" cy="12" r="8.4"/><path d="M9.4 9.4l5.2 5.2M14.6 9.4l-5.2 5.2"/>',
        'activity'        => '<path d="M2.8 12.4h4l2.6-7.2 4.4 13.6 2.4-6.4h4.8"/>',
        'trending-up'     => '<path d="M3.2 16.8l5.4-5.4 3.4 3.4 6.8-6.8"/><path d="M14.6 8h4.2v4.2"/><path d="M3.2 20.4h17.6" opacity=".45"/>',
        'trending-down'   => '<path d="M3.2 7.2l5.4 5.4 3.4-3.4 6.8 6.8"/><path d="M14.6 16h4.2v-4.2"/><path d="M3.2 20.4h17.6" opacity=".45"/>',
        'database'        => '<ellipse cx="12" cy="5.8" rx="7.4" ry="2.9"/><path d="M4.6 5.8v12.4c0 1.6 3.3 2.9 7.4 2.9s7.4-1.3 7.4-2.9V5.8"/><path d="M4.6 12c0 1.6 3.3 2.9 7.4 2.9s7.4-1.3 7.4-2.9"/>',
        'server'          => '<rect x="3.4" y="4" width="17.2" height="6.4" rx="1.6"/><rect x="3.4" y="13.6" width="17.2" height="6.4" rx="1.6"/><path d="M7 7.2h.01M7 16.8h.01M10.6 7.2h4M10.6 16.8h4"/>',
        'clock'           => '<circle cx="12" cy="12" r="8.4"/><path d="M12 7.4V12l3.2 1.9"/>',
        'battery'         => '<rect x="2.8" y="8" width="15.6" height="8" rx="2"/><path d="M21.2 11v2"/><path d="M6 11h7v2H6z" fill="currentColor" stroke="none"/>',
        'wifi-off'        => '<path d="M3.4 9.3a13 13 0 0 1 6-2.6M17.4 11a8.4 8.4 0 0 0-2.4-1.5"/><path d="M6.5 12.6a8.4 8.4 0 0 1 2-1.2M15.5 11.4a8.4 8.4 0 0 1 2 1.2"/><path d="M9.6 15.9a3.9 3.9 0 0 1 4.8 0"/><path d="M12 19.3h.01"/><path d="M3.4 3.4l17.2 17.2" stroke="currentColor"/>',
        'arrow-right'     => '<path d="M4.4 12h15.2M13.6 6.4l5.6 5.6-5.6 5.6"/>',
        'arrow-left'      => '<path d="M19.6 12H4.4M10.4 6.4 4.8 12l5.6 5.6"/>',
        'arrow-up'        => '<path d="M12 19.6V4.4M6.4 10 12 4.4 17.6 10"/>',
        'arrow-down'      => '<path d="M12 4.4v15.2M6.4 14 12 19.6 17.6 14"/>',
        'chevron-down'    => '<path d="M6.4 9.4 12 15l5.6-5.6"/>',
        'chevron-right'   => '<path d="M9.4 6.4 15 12l-5.6 5.6"/>',
        'menu'            => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'           => '<path d="M6.2 6.2l11.6 11.6M17.8 6.2 6.2 17.8"/>',
        'search'          => '<circle cx="10.8" cy="10.8" r="6.4"/><path d="M15.4 15.4l4.4 4.4"/>',
        'plus'            => '<path d="M12 4.6v14.8M4.6 12h14.8"/>',
        'minus'           => '<path d="M4.6 12h14.8"/>',
        'edit'            => '<path d="M4.4 19.6h4l10-10a2.1 2.1 0 0 0-3-3l-10 10z"/><path d="M13.6 6.4l3.4 3.4"/>',
        'trash'           => '<path d="M4.6 7.4h14.8M9.4 7.4V5a1.4 1.4 0 0 1 1.4-1.4h2.4A1.4 1.4 0 0 1 14.6 5v2.4"/><path d="M6.6 7.4l1 11.2a1.6 1.6 0 0 0 1.6 1.4h5.6a1.6 1.6 0 0 0 1.6-1.4l1-11.2"/><path d="M10.4 11v5.2M13.6 11v5.2"/>',
        'download'        => '<path d="M12 4.2v10.4M7.6 10.4 12 14.8l4.4-4.4"/><path d="M4.4 17.4v1.4a1.6 1.6 0 0 0 1.6 1.6h12a1.6 1.6 0 0 0 1.6-1.6v-1.4"/>',
        'upload'          => '<path d="M12 15.2V4.8M7.6 9.2 12 4.8l4.4 4.4"/><path d="M4.4 17.4v1.4a1.6 1.6 0 0 0 1.6 1.6h12a1.6 1.6 0 0 0 1.6-1.6v-1.4"/>',
        'user'            => '<circle cx="12" cy="8.2" r="3.8"/><path d="M4.8 20.2a7.2 7.2 0 0 1 14.4 0"/>',
        'users'           => '<circle cx="9.4" cy="8.4" r="3.4"/><path d="M3.4 19.6a6 6 0 0 1 12 0"/><path d="M16.4 5.4a3.4 3.4 0 0 1 0 6M17.4 14.4a6 6 0 0 1 3.2 5.2"/>',
        'lock'            => '<rect x="4.6" y="10.2" width="14.8" height="9.6" rx="2"/><path d="M8.2 10.2V7.6a3.8 3.8 0 0 1 7.6 0v2.6"/>',
        'key'             => '<circle cx="8" cy="12" r="3.8"/><path d="M11.8 12h8.6M17.6 12v3.2M20 12v2.4"/>',
        'shield'          => '<path d="M12 3.4l7 2.6v5.2c0 4.4-2.9 7.6-7 9.4-4.1-1.8-7-5-7-9.4V6z"/><path d="M9.2 12.2l2 2 3.6-4"/>',
        'layers'          => '<path d="M12 3.4 3.6 7.6 12 11.8l8.4-4.2z"/><path d="M3.6 12.2 12 16.4l8.4-4.2"/><path d="M3.6 16.6 12 20.8l8.4-4.2"/>',
        'map-pin'         => '<path d="M12 21c4-4.6 6-7.7 6-10a6 6 0 1 0-12 0c0 2.3 2 5.4 6 10z"/><circle cx="12" cy="10.8" r="2.4"/>',
        'calendar'        => '<rect x="3.8" y="5.4" width="16.4" height="14.8" rx="2"/><path d="M3.8 10h16.4M8.4 3.6v3.6M15.6 3.6v3.6"/>',
        'filter'          => '<path d="M3.8 5.4h16.4l-6.4 7.6v5.8l-3.6 1.8v-7.6z"/>',
        'zap'             => '<path d="M13.6 3.4 5.4 13.4h5.4l-.8 7.2 8.2-10h-5.4z"/>',
        'refresh'         => '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20.4 4.4v4.2h-4.2"/>',
        'info'            => '<circle cx="12" cy="12" r="8.4"/><path d="M12 11v5.2M12 7.9h.01"/>',
        'external'        => '<path d="M14 4.6h5.4V10"/><path d="M19.4 4.6 11 13"/><path d="M18.2 14v4.4a1.6 1.6 0 0 1-1.6 1.6H5.8a1.6 1.6 0 0 1-1.6-1.6V7.4a1.6 1.6 0 0 1 1.6-1.6h4.4"/>',
        'target'          => '<circle cx="12" cy="12" r="8.2"/><circle cx="12" cy="12" r="4.2"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'route'           => '<circle cx="6" cy="6" r="2.4"/><circle cx="18" cy="18" r="2.4"/><path d="M6 8.4v4.2a4 4 0 0 0 4 4h5.6"/>',
        'wind'            => '<path d="M3.6 8.4h9.2a2.6 2.6 0 1 0-2.6-2.6"/><path d="M3.6 15.6h12.6a2.8 2.8 0 1 1-2.8 2.8"/><path d="M3.6 12h16"/>',
        'eye'             => '<path d="M2.6 12S6 6.4 12 6.4 21.4 12 21.4 12 18 17.6 12 17.6 2.6 12 2.6 12z"/><circle cx="12" cy="12" r="2.8"/>',
    ];

    $key = strtolower(trim((string) $name));
    if ($key === 'sensor' || $key === 'rings') {
        $key = 'sensor-rings';
    }
    if ($key === 'warning' || $key === 'triangle') {
        $key = 'alert-triangle';
    }
    if (!isset($icons[$key])) {
        return '';
    }

    $classes = trim('ico ' . (string) $class);

    return '<svg class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" width="24" height="24" '
        . 'fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $icons[$key] . '</svg>';
}
