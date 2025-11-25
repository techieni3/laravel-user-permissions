<?php

declare(strict_types=1);

namespace Techieni3\LaravelUserPermissions;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use JsonException;
use RuntimeException;

final readonly class PermissionAssets
{
    /**
     * @throws JsonException
     */
    public static function js(): HtmlString
    {
        $content = self::getAssetContents('app.js');

        $variables = Js::from(self::getJavaScriptConfig());

        return new HtmlString(<<<HTML
            <script type="module">
                window.Permissions = {$variables};
                {$content}
            </script>
            HTML);
    }

    public static function css(): HtmlString
    {
        $content = self::getAssetContents('app.css');

        return new HtmlString(<<<HTML
            <style>{$content}</style>
        HTML);
    }

    /**
     * @return array<string, mixed>
     */
    private static function getJavaScriptConfig(): array
    {
        return [
            'path' => config('permissions.dashboard.prefix'),
        ];
    }

    private static function getAssetContents(string $file): string
    {
        $assetPath = __DIR__.'/../dist/'.$file;

        if ( ! file_exists($assetPath) || ($contents = @file_get_contents($assetPath)) === false) {
            throw new RuntimeException("Unable to load the Permissions dashboard asset: {$file}.");
        }

        return $contents;
    }
}
