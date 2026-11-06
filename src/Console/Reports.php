<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Report;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * omnibase's Report (its plan "omnibase agnostic", step F5e, slice 3: Base\Console\Task\*) printed on artisan's
 * output (a SymfonyStyle) as omnibridge/symfony prints it on Symfony's console: text as it is at its verbosity,
 * PSR-3 levels as blocks, a table as Table draws it; the styles of the tasks' markup declared.
 */
final class Reports
{
    public static function printer(SymfonyStyle $io): \Closure
    {
        foreach (Report::STYLES as $name => [$foreground, $background, $options]) {
            if (!$io->getFormatter()->hasStyle($name)) {
                $io->getFormatter()->setStyle($name, new OutputFormatterStyle($foreground, $background, $options));
            }
        }

        return static function (array $entry) use ($io): void {
            match ($entry['kind']) {
                Report::TEXT => $io->write($entry['text'], $entry['newline'], $entry['verbosity']),
                Report::SUCCESS_BLOCK => $io->success($entry['text']),
                Report::TABLE => (new Table($io))->setHeaders($entry['headers'])->setRows($entry['rows'])->render(),
                'emergency', 'alert', 'critical', 'error' => $io->error($entry['text']),
                'warning' => $io->warning($entry['text']),
                'notice' => $io->note($entry['text']),
                'info' => $io->info($entry['text']),
                default => $io->comment($entry['text']),
            };
        };
    }
}
