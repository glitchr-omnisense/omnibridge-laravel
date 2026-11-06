<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TranslationTask;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Composer\Autoload\ClassLoader;
use Illuminate\Console\Command;
use Omnibridge\Contract\Locales;

/**
 * An artisan command running one of omnibase's console tasks (its plan "omnibase agnostic", step F5e, slice 3): its
 * options to the task's input, the Report printed by Reports, its status returned - as omnibridge/symfony's command of
 * the same name does.
 */
abstract class TaskCommand extends Command
{
    /** The Report's printer on this command's output. */
    protected function sink(): \Closure
    {
        return Reports::printer($this->output);
    }

    /**
     * The application's App\ folder: config('omnibridge.console.source'), else the first of Composer's App\ folders
     * that has entities, else app/.
     */
    protected function source(): string
    {
        if (\is_string($source = config('omnibridge.console.source'))) {
            return $source;
        }
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4()['App\\'] ?? [] as $folder) {
                if (is_dir($folder.'/Entity')) {
                    return (string) realpath($folder);
                }
            }
        }

        return $this->laravel->path();
    }

    /**
     * @template T of TranslationTask
     *
     * @param class-string<T> $task
     *
     * @return T a translation keys' task on the host's translator and languages
     */
    protected function translationTask(string $task, mixed ...$more): TranslationTask
    {
        return new $task($this->laravel->make(TranslatorInterface::class), $this->laravel->make(LocalizerInterface::class), $this->laravel->make(Locales::class), ...$more);
    }

    /** An option of no value as a string ('' when not given). */
    protected function text(string $option): string
    {
        return (string) $this->option($option);
    }
}
