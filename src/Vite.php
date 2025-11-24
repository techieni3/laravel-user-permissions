<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use RuntimeException;

class Vite
{
    public static function js(): HtmlString
    {
        $path = __DIR__.'/../dist/app.js';

        if ( ! file_exists($path) || ($js = @file_get_contents($path)) === false) {
            throw new RuntimeException('Unable to load the Permissions dashboard JavaScript.');
        }

        $variables = Js::from(static::scriptVariables());

        return new HtmlString(<<<HTML
            <script type="module">
                window.Permissions = {$variables};
                {$js}
            </script>
            HTML);
    }

    public static function css(): HtmlString
    {
        $path = __DIR__.'/../dist/app.css';

        if ( ! file_exists($path) || ($css = @file_get_contents($path)) === false) {
            throw new RuntimeException('Unable to load the Permissions dashboard CSS.');
        }

        return new HtmlString(<<<HTML
            <style>{$css}</style>
        HTML);
    }

    /**
     * @return array<string, mixed>
     */
    public static function scriptVariables(): array
    {
        return [
            'path' => config('permissions.path'),
        ];
    }
}
