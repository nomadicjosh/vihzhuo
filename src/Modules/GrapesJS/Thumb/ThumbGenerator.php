<?php

namespace Vihzhuo\Modules\GrapesJS\Thumb;

use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Modules\GrapesJS\PageRenderer;
use Vihzhuo\ThemeBlock;
use Exception;

class ThumbGenerator
{
    /**
     * @var ?ThemeContract $theme
     */
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
     * @param string $action
     * @return bool
     * @throws Exception
     */
    public function handleThumbRequest(string $action): bool
    {
        phpb_set_in_editmode();

        if ($action === 'renderNextBlockThumb') {
            $this->renderNextBlockThumb();
            exit();
        }
        if ($action !== 'upload' || !isset($_POST) || !isset($_POST['block']) || !isset($_POST['data'])) {
            return false;
        }
        foreach ($this->theme->getThemeBlocks() as $block) {
            if ($_POST['block'] === $block->getSlug()) {
                $this->r_mkdir(dirname($block->getThumbPath()));
                $file = fopen($block->getThumbPath(), "wb");
                fwrite($file, $this->getRawData($_POST['data']));
                fclose($file);
                exit();
            }
        }
        return false;
    }

    /**
     * Create directories recursively.
     *
     * @param string $path     Path to create
     * @param int $mode        Optional permissions
     * @return bool Success
     */
    protected function r_mkdir(string $path, int $mode = 0777): bool
    {
        return is_dir($path) || ( $this->r_mkdir(dirname($path), $mode) && $this->_mkdir($path, $mode) );
    }

    /**
     * Create directory.
     *
     * @param string $path     Path to create
     * @param int $mode        Optional permissions
     * @return bool Success
     */
    protected function _mkdir(string $path, int $mode = 0777): bool
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

            $data = base64_decode($data);
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
    public function renderNextBlockThumb(): void
    {
        foreach ($this->theme->getThemeBlocks() as $block) {
            $this->renderThumbForBlock($block);
        }
    }

    /**
     * Render a thumbnail for the given block, if no thumb is present or if the thumb needs an update.
     *
     * @param ThemeBlock $block
     * @throws Exception
     */
    public function renderThumbForBlock(ThemeBlock $block): void
    {
        phpb_set_in_editmode();

        $thumbPath = $block->getThumbPath();
        if (file_exists($thumbPath)) {
            return;
        }

        $page = phpb_instance('page');
        $page->setData([
            'layout' => 'master',
            'data' => [
                'html' => [
                    0 => '[block slug="' . $block->getSlug() . '"]'
                ]
            ]
        ]);

        $renderer = phpb_instance(PageRenderer::class, [$this->theme, $page]);
        echo $renderer->render();

        $blockSlug = $block->getSlug();
        require __DIR__ . '/generator-view.php';
        exit();
    }

}
