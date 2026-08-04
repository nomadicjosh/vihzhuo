<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS\Thumb;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\Http\Factories\TextResponseFactory;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Core\View;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\ThemeBlock;
use Exception;

class ThumbGenerator
{
    protected ?ThemeContract $theme = null;

    /**
     * ThumbGenerator constructor.
     *
     * @param ThemeContract $theme
     */
    public function __construct(ThemeContract $theme)
    {
        $this->theme = $theme;
    }

    /**
     * Handle requests to render and store block thumbnails.
     *
     * @param ServerRequestInterface $request
     * @param string|null $action
     * @return ResponseInterface|null
     * @throws Exception
     */
    public function handleThumbRequest(ServerRequestInterface $request, ?string $action = null): ?ResponseInterface
    {
        phpb_set_in_editmode();

        if ($action === 'renderNextBlockThumb') {
            return $this->renderNextBlockThumb();
        }
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $blockSlug = $body['block'] ?? null;
        $data = $body['data'] ?? null;
        if ($action !== 'upload' || !is_string($blockSlug) || !is_string($data)) {
            return null;
        }
        foreach ($this->theme->getThemeBlocks() as $block) {
            if ($blockSlug === $block->getSlug()) {
                $this->rmkDir(dirname($block->getThumbPath()));
                $file = fopen($block->getThumbPath(), "wb");
                if ($file === false) {
                    return JsonResponseFactory::create(['error' => 'Thumbnail could not be opened.'], 500);
                }
                fwrite($file, $this->getRawData($data));
                fclose($file);
                return JsonResponseFactory::create(['success' => true]);
            }
        }
        return JsonResponseFactory::create(['error' => 'Block not found.'], 404);
    }

    /**
     * Create directories recursively.
     *
     * @param string $path     Path to create
     * @param int $mode        Optional permissions
     * @return bool Success
     */
    protected function rmkDir(string $path, int $mode = 0777): bool
    {
        return is_dir($path) || ( $this->rmkDir(dirname($path), $mode) && $this->mkDir($path, $mode) );
    }

    /**
     * Create directory.
     *
     * @param string $path     Path to create
     * @param int $mode        Optional permissions
     * @return bool Success
     */
    protected function mkDir(string $path, int $mode = 0777): bool
    {
        $old = umask(0);
        $res = @mkdir($path, $mode);
        umask($old);
        return $res;
    }

    /**
     * Return binary image data from the given base64 encoded string.
     *
     * @param string $base64ImageData
     * @return string
     * @throws Exception
     */
    protected function getRawData(string $base64ImageData): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64ImageData, $type)) {
            $data = substr($base64ImageData, strpos($base64ImageData, ',') + 1);
            $type = strtolower($type[1]);
            if (! in_array($type, ['jpg', 'jpeg', 'png'])) {
                throw new Exception('Invalid image type');
            }

            $data = base64_decode($data, true);
            if ($data === false) {
                throw new Exception('Decode failed');
            }
            return $data;
        }
        throw new Exception('Invalid data URI');
    }

    /**
     * Render the next missing or outdated thumb of the given theme.
     *
     * @throws Exception
     */
    public function renderNextBlockThumb(): ResponseInterface
    {
        foreach ($this->theme->getThemeBlocks() as $block) {
            $response = $this->renderThumbForBlock($block);
            if ($response !== null) {
                return $response;
            }
        }
        return TextResponseFactory::create('', 204);
    }

    /**
     * Render a thumbnail for the given block, if no thumb is present or if the thumb needs an update.
     *
     * @param ThemeBlock $block
     * @return ResponseInterface|null
     * @throws Exception
     */
    public function renderThumbForBlock(ThemeBlock $block): ?ResponseInterface
    {
        phpb_set_in_editmode();

        $thumbPath = $block->getThumbPath();
        if (file_exists($thumbPath)) {
            return null;
        }

        $page = phpb_instance('page');
        if (!$page instanceof PageContract) {
            return TextResponseFactory::create('Page implementation is unavailable.', 500);
        }
        $page->setData([
            'layout' => 'master',
            'data' => [
                'html' => [
                    0 => '[block slug="' . $block->getSlug() . '"]'
                ]
            ]
        ]);

        $renderer = phpb_instance(PageRenderer::class, [$this->theme, $page]);
        if (!$renderer instanceof PageRenderer) {
            return TextResponseFactory::create('Page renderer is unavailable.', 500);
        }

        $blockSlug = $block->getSlug();
        return HtmlResponseFactory::create(
            $renderer->render() . View::render(__DIR__ . '/generator-view.php', ['blockSlug' => $blockSlug])
        );
    }
}
