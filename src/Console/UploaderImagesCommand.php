<?php

namespace Omnibridge\Laravel\Console;

use Base\Console\Task\UploaderImages;
use Base\Console\Task\UploaderImagesCrop;
use Base\Console\Task\UploaderImagesCropInput;
use Base\Console\Task\UploaderImagesInput;
use Base\Database\Repository\Repositories;
use Base\Service\MediaService;
use Base\Service\ParameterBagInterface;
use Omnibridge\Persistence\UnitOfWork;

/**
 * uploader:images and uploader:images:crop on artisan: omnibase's UploaderImages and UploaderImagesCrop tasks, the
 * same Reports as Symfony's commands. The crops are warmed up by a host's media controller, which Laravel has not
 * (the task says so); their renormalisation is asked here, as Symfony's command asks it.
 */
class UploaderImagesCommand extends TaskCommand
{
    protected $signature = 'uploader:images
        {--entity= : Should I consider only a specific entity ?}
        {--property= : Should I consider only a specific property ?}
        {--uuid= : Should I consider a specific uuid ?}
        {--orphans : Do you want to get orphans ?}
        {--delete-orphans : Do you want to delete orphans ?}
        {--warmup : Do you want to warm up image crops ?}
        {--batch : Process data by batch of X entries}
        {--format= : Only consider data with ???x??? format}
        {--cache : Cache data}';

    protected $description = 'The uploaded images, and their thumbnails made in the cache (omnibase)';

    public function handle(): int
    {
        $app = $this->laravel;
        $media = $app->make(MediaService::class);
        $formats = (array) $app->make(ParameterBagInterface::class)->get('base.uploader.formats');
        $options = [$this->source(), $this->option('entity'), $this->option('property'), $this->option('uuid'), (bool) $this->option('orphans'), (bool) $this->option('delete-orphans'), (bool) $this->option('warmup'), (int) $this->option('batch'), $this->option('format') ?: null];

        if ('uploader:images:crop' !== $this->getName()) {
            return (new UploaderImages($app->make(Repositories::class), $media, $formats))->run(new UploaderImagesInput(...$options), $this->sink())->status();
        }

        $definition = null;
        if ($this->option('normalize')) {
            $definition = $this->choice('Please select a resolution class to be used for renormalization.', UploaderImagesCrop::definitions());
            $definition = new $definition();
        }

        return (new UploaderImagesCrop($app->make(Repositories::class), $media, $formats, $app->make(UnitOfWork::class)))
            ->run(new UploaderImagesCropInput(...$options, normalize: null !== $definition, maxDefinition: $definition), $this->sink(), fn (string $question): bool => $this->confirm($question))
            ->status();
    }
}
