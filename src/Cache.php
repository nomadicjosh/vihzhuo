<?php

declare(strict_types=1);

namespace Vihzhuo;

use FilesystemIterator;
use SplFileInfo;
use Vihzhuo\Contracts\CacheContract;

class Cache implements CacheContract
{
    public static int $maxCacheDepth = 7;
    public static int $maxCachedPageVariants = 50;

    /**
     * Return the cached page content for the given relative URL.
     *
     * @param string $relativeUrl
     * @return string|null
     */
    public function getForUrl(string $relativeUrl): ?string
    {
        $currentPageCacheFolder = $this->getPathForUrl($relativeUrl);
        if (is_dir($currentPageCacheFolder)) {

            if (! file_exists($currentPageCacheFolder . '/expires_at.txt')) {
                $this->invalidate($relativeUrl);
                return null;
            }
            $expiresAt = file_get_contents($currentPageCacheFolder . '/expires_at.txt');
            if (!is_string($expiresAt) || !ctype_digit(trim($expiresAt)) || (int) $expiresAt < time()) {
                $this->invalidate($relativeUrl);
                return null;
            }

            $content = file_get_contents($currentPageCacheFolder . '/page.html');
            return is_string($content) ? $content : null;
        }
        // do not load a skeleton page if the request is a skeleton replacement request
        if (phpb_is_skeleton_data_request()) {
            return null;
        }
        // do not load a skeleton page if the user agent does not support it (or disabled otherwise)
        if ($_SESSION['phpb_no_skeletons'] ?? false) {
            return null;
        }
        // check if a skeleton page is available for a part of the given URL
        $depth = 0;
        while ($relativeUrl !== '/' && $depth < 10) {
            $depth++;
            $relativeUrl = dirname($relativeUrl);
            $currentPageCacheFolder = dirname($this->getPathForUrl($relativeUrl)) . "/skeleton-depth{$depth}";
            if (is_dir($currentPageCacheFolder)) {
                return $this->getForUrl($relativeUrl . "/skeleton-depth{$depth}");
            }
        }

        return null;
    }

    /**
     * Store the given page content for the given relative URL.
     *
     * @param string $relativeUrl
     * @param string $pageContent
     * @param int $cacheLifetime
     */
    public function storeForUrl(string $relativeUrl, string $pageContent, int $cacheLifetime): void
    {
        $currentPageCacheFolder = $this->getPathForUrl($relativeUrl, true);
        if (! $this->cachePathCanBeUsed($currentPageCacheFolder)) {
            return;
        }

        $currentPageCacheFolder = $this->relativeToFullCachePath($currentPageCacheFolder);
        if (! is_dir($currentPageCacheFolder)) {
            mkdir($currentPageCacheFolder, 0775, true);
        }
        file_put_contents($currentPageCacheFolder . '/page.html', $pageContent);
        file_put_contents($currentPageCacheFolder . '/url.txt', $relativeUrl);
        file_put_contents($currentPageCacheFolder . '/expires_at.txt', time() + (60 * $cacheLifetime));
    }

    /**
     * Return the cache storage path for the given relative URL.
     *
     * @param string $relativeUrl
     * @param bool $returnRelative
     * @return string
     */
    public function getPathForUrl(string $relativeUrl, bool $returnRelative = false): string
    {
        // map empty url to the - root folder
        $relativeUrl = (empty($relativeUrl) || $relativeUrl === '/') ? '-' : $relativeUrl;

        // use a cache path with folders based on the URL segments,
        // to allow partial cache invalidation with a specific prefix
        $relativeUrlWithoutQueryString = explode('?', $relativeUrl)[0];
        $cachePath = phpb_slug($relativeUrlWithoutQueryString, true);

        // suffix the cache path with a hash of the exact relative URL,
        // to prevent returning wrong content due to slug collisions
        $cachePath .= '/' . sha1($relativeUrl);

        return $returnRelative ? $cachePath : $this->relativeToFullCachePath($cachePath);
    }

    protected function relativeToFullCachePath(string $relativeCachePath): string
    {
        $configuredFolder = phpb_config('cache.folder');
        $cacheFolder = is_string($configuredFolder) ? $configuredFolder : '';
        if (!str_starts_with($relativeCachePath, '/')) {
            $cacheFolder .= '/';
        }
        return $cacheFolder . $relativeCachePath;
    }

    /**
     * Analyse the given cache path to determine whether it can be used, without server/disk space issues.
     * This prevents deep nested cache paths and large numbers of cached pages per path due to query string variations.
     *
     * @param string $cachePath
     * @return bool
     */
    public function cachePathCanBeUsed(string $cachePath): bool
    {
        if (count(explode('/', $cachePath)) > static::$maxCacheDepth) {
            return false;
        }

        $cachePathWithoutHash = dirname($this->relativeToFullCachePath($cachePath));
        $variants = glob("{$cachePathWithoutHash}/*", GLOB_ONLYDIR);
        $numberOfCachedPageVariants = is_array($variants) ? count($variants) : 0;
        return !(is_dir($cachePathWithoutHash) && $numberOfCachedPageVariants >= static::$maxCachedPageVariants);
    }

    /**
     * Invalidate all variants stored for the given page route (i.e. a URL with * and {} placeholders).
     *
     * @param string $route
     */
    public function invalidate(string $route): void
    {
        $staticUrlPrefix1 = explode('*', $route)[0];
        $staticUrlPrefix2 = explode('{', $route)[0];

        $shortestPrefix = $staticUrlPrefix1;
        if (strlen($staticUrlPrefix2) < strlen($staticUrlPrefix1)) {
            $shortestPrefix = $staticUrlPrefix2;
        }

        $cachePathPrefix = dirname($this->getPathForUrl($shortestPrefix));
        $this->removeDirectoryRecursive($cachePathPrefix);
    }

    /**
     * Recursively remove the directory of the given path and all its contents.
     *
     * @param string $path
     * @return bool
     */
    protected function removeDirectoryRecursive(string $path): bool
    {
        $configuredFolder = phpb_config('cache.folder');
        $cacheRoot = is_string($configuredFolder) ? realpath($configuredFolder) : false;
        $target = realpath($path);
        if (
            $cacheRoot === false || $target === false || $target === $cacheRoot
            || !str_starts_with($target, $cacheRoot . DIRECTORY_SEPARATOR)
        ) {
            return false;
        }

        foreach (new FilesystemIterator($target, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isLink() || $entry->isFile()) {
                unlink($entry->getPathname());
                continue;
            }
            $this->removeDirectoryRecursive($entry->getPathname());
        }

        return rmdir($target);
    }
}
