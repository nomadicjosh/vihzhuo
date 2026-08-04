<?php

declare(strict_types=1);

use Vihzhuo\Extensions;
use Vihzhuo\PageTranslation;
use Psr\Http\Message\ResponseInterface;
use Qubus\Http\Factories\RedirectResponseFactory;
use Vihzhuo\Core\HttpContext;

if (! function_exists('phpb_e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param mixed $value
     * @param bool $doubleEncode
     * @return string
     */
    function phpb_e(mixed $value, bool $doubleEncode = true): string
    {
        $stringValue = is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
        return htmlspecialchars($stringValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (! function_exists('phpb_encode_or_null')) {
    /**
     * Encode HTML special characters in a string, but preserve a null value if the passed input equals null.
     *
     * @param mixed $value
     * @param bool $doubleEncode
     * @return string|null
     */
    function phpb_encode_or_null(mixed $value, bool $doubleEncode = true): ?string
    {
        return $value === null ? null : phpb_e($value, $doubleEncode);
    }
}

if (! function_exists('phpb_json')) {
    /** Encode a value for safe embedding in an HTML script element.
     *
     * @throws JsonException
     */
    function phpb_json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );
    }
}

if (! function_exists('phpb_asset')) {
    /**
     * Return the public path of a PHPageBuilder asset.
     *
     * @param string $path
     * @return string
     */
    function phpb_asset(string $path): string
    {
        $basePath = __DIR__ . '/../../dist/';
        $distPath = realpath($basePath . $path);
        $realBasePath = realpath($basePath);
        $version = ($distPath !== false && $realBasePath !== false && str_starts_with($distPath, $realBasePath)) ? filemtime($distPath) : '';
        $assetsUrl = phpb_config('general.assets_url');
        return phpb_full_url((is_string($assetsUrl) ? $assetsUrl : '/assets') . '/' . $path) . '?v=' . $version;
    }
}

if (! function_exists('phpb_theme_asset')) {
    /**
     * Return the public path of an asset of the current theme.
     *
     * @param string $path
     * @return string
     */
    function phpb_theme_asset(string $path): string
    {
        $folderUrl = phpb_config('theme.folder_url');
        $activeTheme = phpb_config('theme.active_theme');
        $themeFolder = (is_string($folderUrl) ? $folderUrl : '/themes') . '/' . (is_string($activeTheme) ? $activeTheme : '');
        return phpb_full_url($themeFolder . '/' . $path);
    }
}

if (! function_exists('phpb_flash')) {
    /**
     * Return the flash data with the given key (as dot-separated multidimensional array selector) or false if not set.
     *
     * @param string $key
     * @param bool $encode
     * @return bool|mixed
     */
    function phpb_flash(string $key, bool $encode = true): mixed
    {
        global $phpb_flash;
        $flash = is_array($phpb_flash ?? null) ? $phpb_flash : [];

        // if no dot notation is used, return first dimension value or empty string
        if (!str_contains($key, '.')) {
            if (! isset($flash[$key])) {
                return false;
            }
            return $encode ? phpb_e($flash[$key]) : $flash[$key];
        }

        // if dot notation is used, traverse config string
        $segments = explode('.', $key);
        $subArray = $flash;
        foreach ($segments as $segment) {
            if (is_array($subArray) && isset($subArray[$segment])) {
                $subArray = &$subArray[$segment];
            } else {
                return false;
            }
        }

        // if the remaining sub array is a string, return this piece of flash data
        if (is_string($subArray)) {
            if ($encode) {
                return phpb_e($subArray);
            }
            return $subArray;
        }
        return false;
    }
}

if (! function_exists('phpb_config')) {
    /**
     * Return the configuration with the given key (as dot-separated multidimensional array selector).
     *
     * @param string $key
     * @return mixed
     */
    function phpb_config(string $key): mixed
    {
        global $phpb_config;
        $config = is_array($phpb_config ?? null) ? $phpb_config : [];

        // if no dot notation is used, return first dimension value or empty string
        if (!str_contains($key, '.')) {
            return $config[$key] ?? '';
        }

        // if dot notation is used, traverse config string
        $segments = explode('.', $key);
        $subArray = $config;
        foreach ($segments as $segment) {
            if (is_array($subArray) && isset($subArray[$segment])) {
                $subArray = &$subArray[$segment];
            } else {
                return '';
            }
        }

        return $subArray;
    }
}

if (! function_exists('phpb_trans')) {
    /**
     * Return the translation of the given key (as dot-separated multidimensional array selector).
     *
     * @param array<string, scalar|null> $parameters
     * @return array<string, mixed>|string
     */
    function phpb_trans(string $key, array $parameters = []): string|array
    {
        global $phpb_translations;
        $translations = is_array($phpb_translations ?? null) ? $phpb_translations : [];

        // if no dot notation is used, return first dimension value or empty string
        if (!str_contains($key, '.')) {
            $value = $translations[$key] ?? '';
            if (is_string($value)) {
                return phpb_replace_placeholders($value, $parameters);
            }
            return is_array($value) ? array_filter($value, 'is_string', ARRAY_FILTER_USE_KEY) : '';
        }

        // if dot notation is used, traverse translations string
        $segments = explode('.', $key);
        $subArray = $translations;
        foreach ($segments as $segment) {
            if (is_array($subArray) && isset($subArray[$segment])) {
                $subArray = &$subArray[$segment];
            } else {
                return '';
            }
        }

        // if the remaining sub array is a non-empty string/array, return this translation or translations structure
        if (! empty($subArray)) {
            if (is_string($subArray)) {
                return phpb_replace_placeholders($subArray, $parameters);
            }
            return is_array($subArray) ? array_filter($subArray, 'is_string', ARRAY_FILTER_USE_KEY) : '';
        }
        return '';
    }
}

if (! function_exists('phpb_replace_placeholders')) {
    /**
     * Replace in the given string the given parameter placeholders with corresponding values.
     *
     * @param string $string
     * @param array<string, scalar|null> $parameters
     * @return string
     */
    function phpb_replace_placeholders(string $string, array $parameters = []): string
    {
        foreach ($parameters as $placeholder => $value) {
            $string = str_replace(':' . $placeholder, (string) $value, $string);
        }
        return $string;
    }
}


if (! function_exists('phpb_full_url')) {
    /**
     * Give the full URL of a given URL which is relative to the base URL.
     *
     * @param string $urlRelativeToBaseUrl
     * @return string
     */
    function phpb_full_url(string $urlRelativeToBaseUrl): string
    {
        // if the URL is already a full URL, do not alter the URL
        if (str_starts_with($urlRelativeToBaseUrl, 'http://') || str_starts_with($urlRelativeToBaseUrl, 'https://')) {
            return $urlRelativeToBaseUrl;
        }

        $baseUrl = phpb_config('general.base_url');
        if (!is_string($baseUrl)) {
            return $urlRelativeToBaseUrl;
        }
        return rtrim($baseUrl, '/') . $urlRelativeToBaseUrl;
    }
}

if (! function_exists('phpb_url')) {
    /**
     * Give the full URL of a given public path.
     *
     * @param string $module
     * @param array<string, scalar|null> $parameters
     * @param bool $fullUrl
     * @return string
     */
    function phpb_url(string $module, array $parameters = [], bool $fullUrl = true): string
    {
        $url = $fullUrl ? phpb_full_url('') : '';
        $moduleUrl = phpb_config($module . '.url');
        $url .= is_string($moduleUrl) ? $moduleUrl : '';

        if (! empty($parameters)) {
            $url .= '?';
            $pairs = [];
            foreach ($parameters as $key => $value) {
                $pairs[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
            }
            $url .= implode('&', $pairs);
        }

        return $url;
    }
}

if (! function_exists('phpb_current_full_url')) {
    /**
     * Give the current full URL.
     *
     * @param bool $includeQueryString
     * @return string|null
     */
    function phpb_current_full_url(bool $includeQueryString = true): ?string
    {
        if (!HttpContext::hasRequest()) {
            return null;
        }

        $uri = HttpContext::request()->getUri();
        $currentFullUrl = urldecode((string) $uri);
        $currentFullUrl = rtrim($currentFullUrl, '/' . DIRECTORY_SEPARATOR);
        if (! $includeQueryString) {
            $currentFullUrl = (string) preg_replace('/\?.*$/', '', $currentFullUrl);
        }
        if (phpb_is_skeleton_data_request()) {
            return str_replace('/skeleton-data/', '/', $currentFullUrl);
        }
        return $currentFullUrl;
    }
}

if (! function_exists('phpb_current_relative_url')) {
    /**
     * Give the current URL relative to the base directory (the website's index.php entry point).
     * This omits any parent directories from the URL in which the project is installed.
     *
     * @return string
     */
    function phpb_current_relative_url(): string
    {
        if (!HttpContext::hasRequest()) {
            return '/';
        }
        $uri = HttpContext::request()->getUri();
        $relativeUrl = $uri->getPath() ?: '/';
        $baseUrl = phpb_config('general.base_url');
        $configuredBasePath = is_string($baseUrl) ? parse_url($baseUrl, PHP_URL_PATH) : null;
        $basePath = is_string($configuredBasePath) ? rtrim($configuredBasePath, '/') : '';
        if ($basePath !== '' && ($relativeUrl === $basePath || str_starts_with($relativeUrl, $basePath . '/'))) {
            $relativeUrl = substr($relativeUrl, strlen($basePath)) ?: '/';
        }
        $relativeUrl = '/' . ltrim($relativeUrl, '/');
        if ($relativeUrl !== '/') {
            $relativeUrl = rtrim($relativeUrl, '/');
        }
        return $uri->getQuery() === '' ? $relativeUrl : $relativeUrl . '?' . $uri->getQuery();
    }
}

if (! function_exists('phpb_is_skeleton_data_request')) {
    /**
     * Return whether the current request is a request to retrieve skeleton data.
     *
     * @return bool
     */
    function phpb_is_skeleton_data_request(): bool
    {
        $skeletonDataPrefix = '/skeleton-data/';
        return str_starts_with(phpb_current_relative_url(), $skeletonDataPrefix);
    }
}

if (! function_exists('phpb_current_language')) {
    /**
     * Give the current language based on the current URL.
     *
     * @return string
     */
    function phpb_current_language(): string
    {
        $urlComponents = explode('/', phpb_current_relative_url());
        // remove empty values and reset array key numbering
        $urlComponents = array_values(array_filter($urlComponents));
        if (! empty($urlComponents)) {
            foreach (phpb_active_languages() as $languageCode => $languageTranslation) {
                if ($urlComponents[0] === $languageCode) {
                    return $languageCode;
                }
            }
        }
        $configuredLanguage = phpb_config('general.language');
        $languageCode = is_string($configuredLanguage) ? $configuredLanguage : 'en';
        if (array_key_exists($languageCode, phpb_active_languages())) {
            return $languageCode;
        }
        if (in_array('en', array_keys(phpb_active_languages()))) {
            return 'en';
        }
        return array_keys(phpb_active_languages())[0];
    }
}

if (! function_exists('phpb_in_module')) {
    /**
     * Return whether we are currently accessing the given module.
     *
     * @param string $module
     * @return bool
     */
    function phpb_in_module(string $module): bool
    {
        $url = rtrim(phpb_url($module, [], false), '/') ?: '/';
        $currentUrl = explode('?', phpb_current_relative_url(), 2)[0];
        return $currentUrl === $url;
    }
}

if (! function_exists('phpb_on_url')) {
    /**
     * Return whether we are currently on the given URL.
     *
     * @param string $module
     * @param array<string, scalar|null> $parameters
     * @return bool
     */
    function phpb_on_url(string $module, array $parameters = []): bool
    {
        $url = phpb_url($module, $parameters, false);
        return phpb_current_relative_url() === $url;
    }
}

if (! function_exists('phpb_set_in_editmode')) {
    /**
     * Set whether the current page is being load in edit mode (i.e. inside the page builder).
     *
     * @param bool $inEditMode
     */
    function phpb_set_in_editmode(bool $inEditMode = true): void
    {
        global $phpb_in_editmode;

        $phpb_in_editmode = $inEditMode;
    }
}

if (! function_exists('phpb_in_editmode')) {
    /**
     * Return whether the current page is load in edit mode (i.e. inside the page builder).
     *
     * @return bool
     */
    function phpb_in_editmode(): bool
    {
        global $phpb_in_editmode;

        return is_bool($phpb_in_editmode ?? null) ? $phpb_in_editmode : false;
    }
}

if (! function_exists('phpb_redirect')) {
    /**
     * Redirect to the given URL with optional session flash data.
     *
     * @param string $url
     * @param array<string, mixed> $flashData
     * @param int $statusCode
     * @return ResponseInterface
     */
    function phpb_redirect(string $url, array $flashData = [], int $statusCode = 302): ResponseInterface
    {
        if (! empty($flashData)) {
            $_SESSION["phpb_flash"] = $flashData;
        }

        return RedirectResponseFactory::create($url, $statusCode);
    }
}

if (! function_exists('phpb_route_parameters')) {
    /**
     * Return the named route parameters resolved from the current URL.
     *
     * @return array<string, string>
     */
    function phpb_route_parameters(): array
    {
        global $phpb_route_parameters;

        $parameters = is_array($phpb_route_parameters ?? null) ? $phpb_route_parameters : [];
        return array_filter($parameters, function ($value, $key) {
            return is_string($key) && is_string($value);
        }, ARRAY_FILTER_USE_BOTH);
    }
}

if (! function_exists('phpb_route_parameter')) {
    /**
     * Return the value of the given named route parameter resolved from the current URL.
     *
     * @param string $parameter
     * @return string|null
     */
    function phpb_route_parameter(string $parameter): ?string
    {
        global $phpb_route_parameters;

        return phpb_route_parameters()[$parameter] ?? null;
    }
}

if (! function_exists('phpb_field_value')) {
    /**
     * Return the posted value or the attribute value of the given instance, or null if no value was found.
     *
     * @param string $attribute
     * @param object|null $instance
     * @return string|null
     */
    function phpb_field_value(string $attribute, ?object $instance = null): ?string
    {
        $postedValue = HttpContext::hasRequest() ? HttpContext::body($attribute) : null;
        if ($postedValue !== null) {
            return phpb_encode_or_null($postedValue);
        }
        if (isset($instance)) {
            if (method_exists($instance, 'get')) {
                return phpb_encode_or_null($instance->get($attribute));
            }
            return phpb_encode_or_null($instance->{$attribute} ?? null);
        }
        return null;
    }
}

if (! function_exists('phpb_active_languages')) {
    /**
     * Return the list of all active languages.
     *
     * @return array<string, string>
     */
    function phpb_active_languages(): array
    {
        $configuredLanguage = phpb_config('general.language');
        $configLanguageCode = is_string($configuredLanguage) ? $configuredLanguage : 'en';
        $settingClass = phpb_static('setting');
        $languages = $settingClass !== null ? $settingClass::get('languages') : null;
        $languages = is_array($languages) ? $languages : [$configLanguageCode];
        $normalized = [];

        // if the array has numeric indices (which is the default), create a languageCode => languageTranslation structure
        if (array_values($languages) === $languages) {
            foreach ($languages as $languageCode) {
                if (!is_string($languageCode)) {
                    continue;
                }
                $translations = phpb_trans('languages');
                $translation = is_array($translations) ? ($translations[$languageCode] ?? $languageCode) : $languageCode;
                $normalized[$languageCode] = is_string($translation) ? $translation : $languageCode;
            }
        } else {
            foreach ($languages as $languageCode => $translation) {
                if (is_string($languageCode)) {
                    $normalized[$languageCode] = is_string($translation) ? $translation : $languageCode;
                }
            }
        }
        $languages = $normalized;

        if (! isset($languages[$configLanguageCode])) {
            return $languages;
        }

        // sort languages, starting by the configured language
        $languagesSorted[$configLanguageCode] = $languages[$configLanguageCode];
        foreach ($languages as $languageCode => $languageTranslation) {
            if ($languageCode !== $configLanguageCode) {
                $languagesSorted[$languageCode] = $languageTranslation;
            }
        }
        return $languagesSorted;
    }
}

if (! function_exists('phpb_instance')) {
    /**
     * Return an instance of the given class as defined in config,
     * or with the given namespace (which is potentially overridden
     * and mapped to an alternative namespace).
     *
     * @param string $name The name of the config main section in which the class path is defined
     * @param list<mixed> $params
     * @return object|null
     */
    function phpb_instance(string $name, array $params = []): ?object
    {
        $configuredClass = phpb_config($name . '.class');
        if (is_string($configuredClass) && class_exists($configuredClass)) {
            $className = $configuredClass;
            return new $className(...$params);
        }
        if (class_exists($name)) {
            $configuredReplacement = phpb_config('class_replacements.' . $name);
            if (is_string($configuredReplacement) && class_exists($configuredReplacement)) {
                $replacement = $configuredReplacement;
                return new $replacement(...$params);
            }
            return new $name(...$params);
        }
        return null;
    }
}

if (! function_exists('phpb_static')) {
    /**
     * Return a static reference of the given class as defined in config,
     * or with the given namespace (which is potentially overridden and
     * mapped to an alternative namespace).
     *
     * @param string $name The name of the config main section in which the class path is defined
     * @return class-string|null
     */
    function phpb_static(string $name): ?string
    {
        if (phpb_config($name . '.class')) {
            $class = phpb_config($name . '.class');
            return is_string($class) && class_exists($class) ? $class : null;
        }
        if (class_exists($name)) {
            if (phpb_config('class_replacements.' . $name)) {
                $replacement = phpb_config('class_replacements.' . $name);
                return is_string($replacement) && class_exists($replacement) ? $replacement : null;
            }
            return $name;
        }
        return null;
    }
}

if (! function_exists('phpb_slug')) {
    /**
     * Create a slug (safe URL or path) of the given string.
     *
     * @param string $text
     * @param bool $allowSlashes
     * @return string
     */
    function phpb_slug(string $text, bool $allowSlashes = false): string
    {
        if ($allowSlashes) {
            return strtolower(trim((string) preg_replace('/[^A-Za-z0-9-\/]+/', '-', $text)));
        }
        return strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
    }
}

if (! function_exists('phpb_autoload')) {
    /**
     * Autoload classes from the PHPageBuilder package.
     *
     * @param  string $className
     */
    function phpb_autoload(string $className): void
    {
        // PSR-0 autoloader
        $className = ltrim($className, '\\');
        $fileName  = '';
        $namespace = '';
        if ($lastNsPos = strripos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

        // remove leading PHPageBuilder/ from the class path
        $fileName = str_replace('PHPageBuilder' . DIRECTORY_SEPARATOR, '', $fileName);

        // include class files starting in the src directory
        require __DIR__ . '/../' . $fileName;
    }
}

if (! function_exists('phpb_registered_assets')) {
    /**
     * Render all manually registered assets.
     *
     * @param string $location
     * @return void
     */
    function phpb_registered_assets(string $location = 'header'): void
    {
        $assets = ($location === 'header') ? Extensions::getHeaderAssets() : Extensions::getFooterAssets();

        foreach ($assets as $asset) {
            $attributes = '';
            foreach ($asset['attributes'] as $key => $value) {
                $attributes .= ' ' . $key . '="' . $value . '"';
            }

            if ($asset['type'] == 'style') {
                echo '<link rel="stylesheet" href="' . $asset['src'] . '" ' . $attributes . '/>';
            } else {
                echo '<script type="text/javascript" src="' . $asset['src'] . '" ' . $attributes . '></script>';
            }
        }
    }
}

if (! function_exists('phpb_pages')) {
    /**
     * Return navigation array.
     *
     * @return list<array<string, mixed>>
     */
    function phpb_pages(): array
    {
        $array = new PageTranslation()->getPages();
        $pages = [];
        foreach ($array as $key => $value) {
            $pages[] = $value;
        }

        return $pages;
    }
}
