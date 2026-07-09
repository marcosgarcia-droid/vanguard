<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>VANGUARD</title>

    <meta name="theme-color" content="#05070A">

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>

<body class="portal">

    @yield('content')

</body>

</html>