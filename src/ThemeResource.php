<?php

declare(strict_types=1);

namespace Vihzhuo;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Vihzhuo\Contracts\ThemeContract;
use Vihzhuo\Contracts\ThemeInheritanceContract;

/** @internal Shared file and configuration resolution for blocks and layouts. */
final class ThemeResource
{
    /** @var list<string> */
    private array $folders = [];

    /** @var array<string, array<string, mixed>> */
    private array $configs = [];

    /** @var array<string, mixed> */
    private array $config = [];

    private string $fallbackFolder;

    public function __construct(ThemeContract $theme, string $type, string $slug, ?string $extensionPath = null)
    {
        if ($extensionPath !== null) {
            $this->fallbackFolder = $extensionPath;
            $this->folders[] = $extensionPath;
        } else {
            self::validateSlug($slug);
            $this->fallbackFolder = $theme->getFolder() . '/' . $type . '/' . $slug;
            foreach (self::themeFolders($theme) as $themeFolder) {
                $paths = $type === 'blocks'
                ? ['blocks/archived', 'blocks/elements', 'blocks/php', 'blocks']
                : ['layouts'];
                foreach ($paths as $path) {
                    $folder = $themeFolder . '/' . $path . '/' . $slug;
                    if (is_dir($folder)) {
                        self::assertContained($themeFolder, $folder);
                        $this->folders[] = $folder;
                        break;
                    }
                }
                if ($this->folders !== [] && $theme instanceof Theme && !$theme->inheritsResourceFiles()) {
                    break;
                }
            }
        }
    }

    /** @param Closure(string): mixed $loader Keeps configuration in the block/layout's original scope. */
    public function loadConfiguration(Closure $loader): void
    {
        // A replacement is a boundary: do not execute configuration further up the chain.
        foreach ($this->folders as $index => $folder) {
            $file = self::findInFolder($folder, 'config.php');
            $config = $file !== null ? $loader($file) : [];
            $this->configs[$folder] = is_array($config) ? array_filter($config, 'is_string', ARRAY_FILTER_USE_KEY) : [];
            if (($this->configs[$folder]['inherit'] ?? true) === false) {
                $this->folders = array_slice($this->folders, 0, $index + 1);
                break;
            }
        }
        foreach (array_reverse($this->folders) as $folder) {
            $this->config = self::mergeConfig($this->config, $this->configs[$folder]);
        }
    }

    public static function validateSlug(string $slug): void
    {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/D', $slug) !== 1) {
            throw new InvalidArgumentException('Invalid theme, block or layout slug: ' . $slug);
        }
    }

    /** @return array<string, string> */
    public static function themeFolders(ThemeContract $theme): array
    {
        return $theme instanceof ThemeInheritanceContract ? $theme->getThemeFolders() : ['' => $theme->getFolder()];
    }

    public static function assertContained(string $root, string $path): void
    {
        $realRoot = realpath($root);
        $realPath = realpath($path);
        if (
            $realRoot === false || $realPath === false
            || !str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('Theme path escapes its configured directory: ' . $path);
        }
    }

    public static function findInFolder(string $folder, string $path): ?string
    {
        self::validatePath($path);
        $file = $folder . '/' . $path;
        if (!is_file($file)) {
            return null;
        }
        self::assertContained($folder, $file);
        return $file;
    }

    public static function validatePath(string $path): void
    {
        if (str_contains($path, '\\') || str_contains($path, "\0") || str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Theme paths must be relative.');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new InvalidArgumentException('Theme paths cannot contain traversal segments.');
            }
        }
    }

    /** Resolve an asset while preserving its original URL encoding, query and fragment. */
    public static function assetPath(ThemeContract $theme, string $path, string $baseUrl, string $fallbackSlug): string
    {
        $filePath = preg_split('/[?#]/', $path, 2)[0] ?? '';
        $filePath = rawurldecode($filePath);
        self::validatePath($filePath);
        $slug = $fallbackSlug;
        foreach (self::themeFolders($theme) as $candidate => $folder) {
            if ($filePath !== '' && self::findInFolder($folder, $filePath) !== null) {
                $slug = $candidate !== '' ? $candidate : $fallbackSlug;
                break;
            }
        }
        return rtrim($baseUrl, '/') . '/' . $slug . '/' . $path;
    }

    /** @param list<string> $names */
    public function findFile(array $names): ?string
    {
        foreach ($this->folders as $folder) {
            foreach ($names as $name) {
                $file = self::findInFolder($folder, $name);
                if ($file !== null) {
                    return $file;
                }
            }
        }
        return null;
    }

    /** Fingerprint only the effective resource layers, including PHP code and scripts. */
    public function fingerprint(): string
    {
        $hash = hash_init('sha256');
        $names = [
            'config.php', 'view.php', 'view.html', 'model.php', 'controller.php',
            'script.php', 'script.html', 'script.js',
            'builder-script.php', 'builder-script.html', 'builder-script.js',
        ];
        foreach ($this->folders as $folder) {
            foreach ($names as $name) {
                $file = self::findInFolder($folder, $name);
                if ($file !== null) {
                    hash_update($hash, $file . "\0" . (string) file_get_contents($file) . "\0");
                }
            }
        }
        return hash_final($hash);
    }

    public function getFolder(): string
    {
        return $this->folders[0] ?? $this->fallbackFolder;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** Namespace metadata belongs to the PHP file's layer, not an overriding view. */
    public function getFileNamespace(string $file): ?string
    {
        $sourceFound = false;
        foreach ($this->folders as $folder) {
            $sourceFound = $sourceFound || dirname($file) === $folder;
            if ($sourceFound && isset($this->configs[$folder]['namespace'])) {
                $namespace = $this->configs[$folder]['namespace'];
                return is_string($namespace) ? $namespace : '';
            }
        }
        return null;
    }

    /**
     * Merge maps recursively; lists, empty arrays, null and scalars replace the parent value.
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $child
     * @return array<string, mixed>
     */
    private static function mergeConfig(array $parent, array $child): array
    {
        foreach ($child as $key => $value) {
            $previous = $parent[$key] ?? null;
            if (is_array($value) && !array_is_list($value) && is_array($previous) && !array_is_list($previous)) {
                $parent[$key] = self::mergeConfig(
                    array_filter($previous, 'is_string', ARRAY_FILTER_USE_KEY),
                    array_filter($value, 'is_string', ARRAY_FILTER_USE_KEY)
                );
            } else {
                $parent[$key] = $value;
            }
        }
        return $parent;
    }
}
