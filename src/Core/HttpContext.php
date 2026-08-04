<?php

declare(strict_types=1);

namespace Vihzhuo\Core;

use LogicException;
use Psr\Http\Message\ServerRequestInterface;

final class HttpContext
{
    private static ?ServerRequestInterface $request = null;

    public static function setRequest(ServerRequestInterface $request): void
    {
        self::$request = $request;
    }

    public static function request(): ServerRequestInterface
    {
        return self::$request ?? throw new LogicException('No active PSR-7 server request.');
    }

    public static function hasRequest(): bool
    {
        return self::$request !== null;
    }

    public static function query(string $name): mixed
    {
        return self::request()->getQueryParams()[$name] ?? null;
    }

    public static function body(string $name): mixed
    {
        $body = self::request()->getParsedBody();
        return is_array($body) ? ($body[$name] ?? null) : null;
    }
}
