<?php

namespace Omnibridge\Laravel\Static;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request as LaravelRequest;
use Omnibridge\Laravel\Routing\ActionRoutes;
use Omnibridge\Static\Console\Cli;
use Omnibridge\Static\Render\RequestRenderer;
use Symfony\Component\HttpFoundation\Request;

/**
 * omnibridge/static's build under Laravel: the same Console\Cli as the plain
 * command line, handed what this host offers the site's file - a renderer
 * through the application's HTTP kernel (each page a visitor's GET, at
 * --render-origin, by default config('app.url')), the container, the public
 * folder and, given --actions, the routes the actions of those folders
 * declare (Routing\ActionRoutes, as routes/web.php registers them).
 *
 *     php artisan omnibridge:static:build static.php --out=storage/static --actions='App\Action\=src/Action'
 *
 * Registered by OmnibridgeServiceProvider only where omnibridge/static is installed.
 */
final class BuildCommand extends Command
{
    protected $signature = 'omnibridge:static:build
        {site=static.php : The file that declares the site (it returns an Omnibridge\Static\Site, or a closure that takes the host\'s offer)}
        {--out=storage/static : The folder to export to (emptied first if it holds an earlier export)}
        {--render-origin= : The origin the pages are rendered at (config app.url if not given)}
        {--actions=* : namespace=folder of the actions whose #[Route] the site\'s file is offered (App\Action\=src/Action)}
        {--verify : Render every page twice; a page that differs is an error}
        {--strict : A warning fails the build too}
        {--quiet-progress : Errors and counts only}';

    protected $description = 'Exports the site a static.php declares to a folder for a static host (omnibridge/static).';

    public function handle(): int
    {
        $origin = $this->option('render-origin');
        $url = config('app.url');
        $origin = \is_string($origin) && '' !== $origin ? $origin : (\is_string($url) ? $url : 'http://localhost');
        $app = $this->laravel;

        $host = [
            Cli::HOST_NAME => 'laravel',
            Cli::HOST_RENDERER => new RequestRenderer(static fn (Request $request) => $app->make(Kernel::class)->handle(LaravelRequest::createFromBase($request)), $origin),
            Cli::HOST_CONTAINER => $app,
            Cli::HOST_PUBLIC_DIR => public_path(),
        ];
        $folders = [];
        foreach ((array) $this->option('actions') as $pair) {
            if (\is_string($pair) && str_contains($pair, '=')) {
                [$namespace, $folder] = explode('=', $pair, 2);
                $folders[$namespace] = str_starts_with($folder, '/') ? $folder : base_path($folder);
            }
        }
        if ($folders) {
            $host[Cli::HOST_ROUTES] = ActionRoutes::load($folders, ActionRoutes::environment((string) $app->environment()));
        }

        $out = $this->option('out');
        $options = array_filter([
            'out' => \is_string($out) && !str_starts_with($out, '/') ? base_path($out) : $out,
            'verify' => $this->option('verify') ?: null,
            'strict' => $this->option('strict') ?: null,
            'quiet' => $this->option('quiet-progress') ?: null,
        ], static fn (mixed $value) => null !== $value);
        $site = $this->argument('site');
        $site = \is_string($site) ? $site : 'static.php';

        $cli = new Cli(function (string $line): void {
            $this->line($line);
        });

        try {
            /** @var array<string, string|true> $options */
            return $cli->build(str_starts_with($site, '/') ? $site : base_path($site), $options, $host);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }
    }
}
