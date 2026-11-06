<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Commands;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * omnibase's Commands (its plan "omnibase agnostic", step F5e, slice 3) on artisan: a command run by its name, its
 * output where the caller prints. The Symfony names a task asks for are artisan's here: the fixtures are the
 * database's seeders (db:seed, without groups), the cache pools Laravel's cache (cache:clear).
 */
final class ArtisanCommands implements Commands
{
    /** @var array<string, array{0: string, 1: array<string, mixed>}> a Symfony command => artisan's, and its arguments */
    public const NAMES = [
        'doctrine:fixtures:load' => ['db:seed', ['--force' => true]],
        'cache:pool:clear' => ['cache:clear', []],
    ];

    public function __construct(private readonly Kernel $artisan, private readonly OutputInterface $output)
    {
    }

    public function run(string $name, array $arguments = []): ?int
    {
        [$name, $arguments] = self::NAMES[$name] ?? [$name, $arguments];
        if (!\array_key_exists($name, $this->artisan->all())) {
            return null;
        }

        return $this->artisan->call($name, $arguments + ['--no-interaction' => true], $this->output);
    }
}
