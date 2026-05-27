<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Plain-PHP view renderer with a single layout wrapper.
 *
 * Templates live in /views and receive $data as extracted variables. The
 * layout receives the rendered template as $content.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderRaw($template, $data);

        if ($layout === null) {
            return $content;
        }
        return self::renderRaw($layout, array_merge($data, ['content' => $content]));
    }

    /** Render a template file without a layout. */
    public static function renderRaw(string $template, array $data = []): string
    {
        $file = config('paths.base') . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /** Render a partial inline (for use inside templates). */
    public static function partial(string $template, array $data = []): void
    {
        echo self::renderRaw($template, $data);
    }
}
