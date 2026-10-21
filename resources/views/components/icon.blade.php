@props(['name', 'set' => null])
@php
    // Design notes §4 — two complete, client-selectable icon sets. The playful
    // plumbing set overrides the standard glyph where one was decided; anything
    // else falls back to the standard set so every symbol is always present.
    $set ??= \App\Models\Tenant::current()?->settings['icon_set'] ?? 'plumbing';
    $view = "components.icons.{$set}.{$name}";
    if (! view()->exists($view)) {
        $view = "components.icons.standard.{$name}";
    }
    if (! view()->exists($view)) {
        $view = 'components.icons.standard.dot';
    }
@endphp
@include($view, ['attributes' => $attributes])
