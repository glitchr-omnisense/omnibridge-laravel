<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Form;

use Base\Field\Type\SelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** A select of the application's own (glitchr's SoftwareFamilyType): omnibase's SelectType as its parent, its choices. */
final class VisitorsType extends AbstractType
{
    public function getParent(): string
    {
        return SelectType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['choices' => ['Ada' => 'ada', 'Grace' => 'grace'], 'multiple' => true, 'required' => false]);
    }
}
