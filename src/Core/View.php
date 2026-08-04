<?php

declare(strict_types=1);

namespace Vihzhuo\Core;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        if (!is_file($view)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $view;
            $content = ob_get_contents();
            if ($content === false) {
                throw new RuntimeException("Unable to render view: {$view}");
            }
            return $content;
        } finally {
            ob_end_clean();
        }
    }
}
