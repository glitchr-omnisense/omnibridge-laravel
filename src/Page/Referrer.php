<?php

namespace Omnibridge\Laravel\Page;

/**
 * app.referrer as omnibase's templates read it (omnibase's ReferrerInterface
 * under Symfony): the page the visitor came from on this site, null for
 * another site or none; printed as that address, or as nothing.
 */
final class Referrer implements \Stringable
{
    public function __construct(private readonly ?string $url)
    {
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function sameSite(): bool
    {
        return null !== $this->url;
    }

    public function __toString(): string
    {
        return (string) $this->url;
    }
}
