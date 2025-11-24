<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>Permissions Manager</title>

        {!! Techieni3\LaravelUserPermissions\Vite::css() !!}
        {!! Techieni3\LaravelUserPermissions\Vite::js() !!}
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
