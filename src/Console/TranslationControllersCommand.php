<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TranslationControllers;
use Base\Console\Task\TranslationControllersInput;
use Illuminate\Routing\Route;

/** translation:controllers on artisan: the application's named routes to omnibase's TranslationControllers task. */
final class TranslationControllersCommand extends TaskCommand
{
    protected $signature = 'translation:controllers
        {--controller= : Should I consider only a specific controller ?}
        {--suffix=title : Should I add a specific translation suffix to the default path ?}
        {--locale= : Should I display only a specific locale ?}';

    protected $description = 'The translation keys of the pages\' titles, by route (omnibase)';

    public function handle(): int
    {
        $routes = [];
        foreach ($this->laravel->make('router')->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (null !== $route->getName()) {
                $routes[$route->getName()] = $route->getActionName();
            }
        }

        return $this->translationTask(TranslationControllers::class)
            ->run(new TranslationControllersInput($routes, $this->text('controller'), $this->text('suffix'), $this->option('locale')), $this->sink())
            ->status();
    }
}
