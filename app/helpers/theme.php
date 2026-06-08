<?php

declare(strict_types=1);

function theme_view(string $view): string
{
    return \App\Extension\ThemeApi::view($view);
}


function theme_assets(): void
{
    try {
        $tm = new \App\Core\ThemeManager();
        $active = $tm->active();
        if ($active === 'default') return;
        $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $active);
        $css = dirname(__DIR__, 2) . '/themes/' . $slug . '/assets/css/theme.css';
        if (is_file($css)) {
            echo \App\Extension\ThemeApi::cssTag('assets/css/theme.css', $slug);
        }
    } catch (\Throwable $e) {
        return;
    }
}
