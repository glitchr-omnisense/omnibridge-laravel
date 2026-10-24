<?php

namespace Omnibridge\Laravel\View;

use Base\Service\Model\EntityNouns;
use Base\Service\Obfuscator;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Translations;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * The filters of omnibase's that its Symfony extensions register over
 * Symfony's services, answered here by the same rules over what Laravel
 * binds (step R27 of the audit "lesringostars face au master plan"):
 * |trans_entity (TranslatorTwigExtension: an entity's noun, omnibase's
 * EntityNouns over the Translations contract) and |obfuscate
 * (MediaTwigExtension: omnibase's Obfuscator, the one omnibridge.media
 * binds - the Symfony site's map, so an address either host wrote resolves
 * on both).
 */
final class OmnibaseFilters extends AbstractExtension
{
    private ?EntityNouns $nouns = null;

    /** @param \Closure(): Obfuscator|null $obfuscator */
    public function __construct(private readonly Translations $translations, private readonly ?\Closure $obfuscator = null)
    {
    }

    public function getFilters(): array
    {
        $filters = [
            new TwigFilter('trans_entity', $this->entity(...)),
            new TwigFilter('trans_entityExists', $this->entityExists(...)),
        ];
        if (null !== $this->obfuscator) {
            $filters[] = new TwigFilter('obfuscate', fn (array $value, bool $short = Obfuscator::NO_SHORT): string => ($this->obfuscator)()->encode($value, $short));
        }

        return $filters;
    }

    /** @param list<string>|string $options */
    public function entity(object|string $entityOrClass, ?string $property = null, string|array $options = TranslatorInterface::NOUN_SINGULAR): ?string
    {
        return $this->nouns()->entity($entityOrClass, $property, $options);
    }

    /** @param list<string>|string $options */
    public function entityExists(object|string $entityOrClass, ?string $property = null, string|array $options = TranslatorInterface::NOUN_SINGULAR): bool
    {
        return $this->nouns()->entityExists($entityOrClass, $property, $options);
    }

    private function nouns(): EntityNouns
    {
        return $this->nouns ??= new EntityNouns($this->translations);
    }
}
