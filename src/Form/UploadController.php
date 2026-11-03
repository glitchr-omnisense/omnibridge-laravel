<?php

namespace Omnibridge\Laravel\Form;

use Base\Field\Search\Answer;
use Base\Field\Upload\Dropzone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * omnibase's dropzone under Laravel (plan "omnibase agnostic", step F5d): each route of
 * Base\Field\Upload\Dropzone::ROUTES, declared under its Symfony name and path, is answered by
 * Dropzone's action for it - a file kept until its form is sent (ux_dropzone), shown again
 * (ux_dropzone_preview), removed (ux_dropzone_delete). The answer is Dropzone's, sent as JSON or
 * as its reason with its status, as omnibase's DropzoneController sends it.
 *
 * In the web group (the session holds the token the payload carries) without Laravel's CSRF
 * middleware: Dropzone checks that token itself, as Symfony's controller does.
 */
final class UploadController
{
    public function __construct(private readonly Dropzone $dropzone)
    {
    }

    public function __invoke(Request $request): Response
    {
        $route = $request->route();
        [, $action] = Dropzone::ROUTES[$route->getName()] ?? throw new \LogicException(\sprintf('"%s" is not a dropzone route.', $route->getName()));
        $data = (string) $route->parameter('data');
        if ('store' === $action) {
            $file = $request->file('file');
            $file = $file instanceof UploadedFile ? $file : null;

            // A file a test hands (UploadedFile::fake(): not received by PHP) is moved as it is, as Symfony's test mode moves it.
            $moved = null !== $file && !is_uploaded_file((string) $file->getRealPath()) ? static fn (string $from, string $to): bool => rename($from, $to) : null;

            return self::send($this->dropzone->store($data, $file, $file?->getError() ?? \UPLOAD_ERR_OK, $moved));
        }
        $uuid = (string) $route->parameter('uuid');
        if ('delete' === $action) {
            return self::send($this->dropzone->delete($data, $uuid));
        }
        $path = $this->dropzone->preview($data, $uuid);
        if ($path instanceof Answer) {
            return self::send($path);
        }
        if (null === $path) {
            throw new NotFoundHttpException();
        }
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => mime_content_type2($path)], true, null, true);
        $response->setMaxAge(300);
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }

    private static function send(Answer $answer): Response
    {
        return \is_array($answer->body) ? new JsonResponse($answer->body, $answer->status) : new Response($answer->body, $answer->status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
