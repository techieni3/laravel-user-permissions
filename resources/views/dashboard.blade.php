<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Permissions Manager</title>

    {{-- Include compiled CSS --}}
    <link href="{{ asset('vendor/permissions/app.css') }}" rel="stylesheet">
</head>
<body>
    <div id="app"></div>

    {{-- Include compiled JavaScript --}}
    <script src="{{ asset('vendor/permissions/app.js') }}"></script>
</body>
</html>
