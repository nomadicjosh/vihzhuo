<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface RouterContract
{
    /**
     * Return the page corresponding to the given URL.
     *
     * @param string $url
     * @return PageTranslationContract|null
     */
    public function resolve(string $url): ?PageTranslationContract;

    /**
     * Return the full page translation instance based on the given matched route or page translation id.
     * (this method is helpful when extending a router to perform additional checks after a route has been matched).
     *
     * @param string $matchedRoute                  The matched route.
     * @param string $matchedPageTranslationId      The page translation id corresponding to the matched route.
     * @return PageTranslationContract|null
     */
    public function getMatchedPage(string $matchedRoute, string $matchedPageTranslationId): ?PageTranslationContract;

    /**
     * Order the given routes into the order in which they need to be evaluated.
     *
     * @param list<list<string>> $allRoutes
     * @return list<list<string>>
     */
    public function getRoutesInOrder(array $allRoutes): array;

    /**
     * Compare two given routes and return -1,0,1 indicating which route should be evaluated first.
     *
     * @param list<string> $route1
     * @param list<string> $route2
     * @return int
     */
    public function routeOrderComparison(array $route1, array $route2): int;
}
