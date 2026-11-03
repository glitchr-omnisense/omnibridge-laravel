<?php

namespace Omnibridge\Laravel\Form;

use Base\Field\Editor\EditorJs;
use Base\Field\Search\Answer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * omnibase's Editor.js routes under Laravel (plan "omnibase agnostic", step F5d, slice 6): each route of
 * Base\Field\Editor\EditorJs::ROUTES, declared under its Symfony name and path, is answered by EditorJs's
 * action for it - the mentions (ux_editorjs_endpointBy*), a picture sent or fetched (ux_editorjs_uploadBy*),
 * a collaboration relay's ticket and a field's autosave. The answer is EditorJs's, sent as JSON or as its
 * reason with its status, as omnibase's EditorController sends it.
 *
 * In the web group (the session holds the tokens the requests carry) without Laravel's CSRF middleware:
 * EditorJs checks the editor's token, or the room's, itself, as Symfony's controller does.
 */
final class EditorController
{
    public function __construct(private readonly EditorJs $editorJs)
    {
    }

    public function __invoke(Request $request): Response
    {
        $route = $request->route();
        [, $action] = EditorJs::ROUTES[$route->getName()] ?? throw new \LogicException(\sprintf('"%s" is not an editor route.', $route->getName()));
        $data = $route->parameter('data');
        $data = \is_string($data) ? $data : null;
        $body = json_decode($request->getContent(), true);
        $body = \is_array($body) ? $body : [];

        $answer = match ($action) {
            'users', 'keywords', 'threads' => $this->editorJs->{$action}($data, $request->getMethod(), $body, $route->parameter('page') ?? 1),
            'uploadByFile' => $this->uploadByFile($request, $data),
            'uploadByUrl' => $this->editorJs->uploadByUrl($data, \is_string($body['url'] ?? null) ? $body['url'] : null),
            'ticket' => $this->editorJs->ticket($body),
            'autosave' => $this->editorJs->autosave($body),
        };

        return \is_array($answer->body) ? new JsonResponse($answer->body, $answer->status) : new \Illuminate\Http\Response($answer->body, $answer->status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function uploadByFile(Request $request, ?string $data): Answer
    {
        $file = $request->file('image');
        $file = $file instanceof UploadedFile ? $file : null;

        return $this->editorJs->uploadByFile($data, $file, $file?->getError() ?? \UPLOAD_ERR_OK);
    }
}
