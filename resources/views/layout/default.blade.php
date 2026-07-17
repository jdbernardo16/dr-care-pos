<?php
$theme  =   ns()->option->get( 'ns_default_theme', 'light' );
?>
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Dr Care">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/icons/apple-touch-icon-180x180.png">
    <link rel="manifest" href="{{ url( 'build/manifest.webmanifest' ) }}">
    <title>{!! $title ?? __( 'Unnamed Page' ) !!}</title>
    @include( 'layout._header-injection' )
    @vite([
        'resources/scss/line-awesome/1.3.0/scss/line-awesome.scss',
        'resources/css/grid.css',
        'resources/css/fonts.css',
        'resources/css/animations.css',
        'resources/css/' . $theme . '.css'
    ])
    @yield( 'layout.base.header' )
</head>
<body>
    @yield( 'layout.default.body' )
    <script>if('serviceWorker'in navigator){window.addEventListener('load',()=>{navigator.serviceWorker.register('/build/sw.js',{scope:'/'})})}</script>
</body>
</html>
