<?php

namespace Omnibridge\Laravel\Map;

use Omnimap\Exception\InvalidArgumentException;
use Omnimap\Snapshot\Snapshots;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** A tile of a map's picture kept in omnimap's cache (MapServiceProvider's tile route), public and cacheable. */
final class TileController
{
    public function __construct(private readonly Snapshots $snapshots)
    {
    }

    public function tile(Request $request, string $signature, int|string $id = 0): Response
    {
        try {
            $bytes = $this->snapshots->store()->tile($signature, (int) $id);
        } catch (InvalidArgumentException) {
            $bytes = null;
        }
        if (null === $bytes) {
            throw new NotFoundHttpException('No such map tile.');
        }
        $format = $this->snapshots->store()->format();
        $response = new Response($bytes, 200, ['Content-Type' => 'image/'.('jpg' === $format ? 'jpeg' : $format)]);
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->setEtag(md5($bytes));
        $response->isNotModified($request);

        return $response;
    }
}
