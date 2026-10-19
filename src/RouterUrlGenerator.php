<?php

namespace Omnibridge\Laravel;

use Omnibridge\Twig\Contract\Urls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Symfony's URL generator interface over the Urls contract, for a library
 * that asks for one (omnibase/consent's extension): an absolute URL is
 * Urls::url(), any other reference type Urls::path().
 */
final class RouterUrlGenerator implements UrlGeneratorInterface
{
    private RequestContext $context;

    public function __construct(private readonly Urls $urls)
    {
        $this->context = new RequestContext();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return self::ABSOLUTE_URL === $referenceType ? $this->urls->url($name, $parameters) : $this->urls->path($name, $parameters);
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }
}
