<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\TranslationSettings;
use Base\Console\Task\TranslationSettingsInput;
use Base\Service\SettingBagInterface;

/** translation:settings on artisan: omnibase's TranslationSettings task, the same Report as Symfony's command. */
final class TranslationSettingsCommand extends TaskCommand
{
    protected $signature = 'translation:settings
        {--path= : Should I consider only a specific setting path ?}
        {--raw : Should I display the raw information ? (including user label and help information)}
        {--locale= : Should I display only a specific locale ?}';

    protected $description = 'The settings and their translations (omnibase)';

    public function handle(): int
    {
        return $this->translationTask(TranslationSettings::class, $this->laravel->make(SettingBagInterface::class))
            ->run(new TranslationSettingsInput($this->option('path'), (bool) $this->option('raw'), $this->option('locale')), $this->sink())
            ->status();
    }
}
