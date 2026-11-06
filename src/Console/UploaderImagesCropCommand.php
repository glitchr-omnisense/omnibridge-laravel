<?php

namespace Omnibridge\Laravel\Console;

/** uploader:images:crop on artisan: UploaderImagesCommand's handle(), on omnibase's UploaderImagesCrop task. */
final class UploaderImagesCropCommand extends UploaderImagesCommand
{
    protected $signature = 'uploader:images:crop
        {--entity= : Should I consider only a specific entity ?}
        {--property= : Should I consider only a specific property ?}
        {--uuid= : Should I consider a specific uuid ?}
        {--orphans : Do you want to get orphans ?}
        {--delete-orphans : Do you want to delete orphans ?}
        {--warmup : Do you want to warm up image crops ?}
        {--batch : Process data by batch of X entries}
        {--format= : Only consider data with ???x??? format}
        {--cache : Cache data}
        {--normalize : Do you want to update coordinate system ?}
        {--max-definition : Which max definition to use ?}';

    protected $description = 'The uploaded images and their crops made in the cache, the crops renormalised (omnibase)';
}
