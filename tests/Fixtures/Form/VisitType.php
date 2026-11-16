<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Form;

use Base\Field\Type\SelectType;
use Base\Field\Type\SubmitType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * A form as a bundle writes one (omnibase/music's TrackType): omnibase's select (Base\Field\Type\SelectType, not
 * installed under Laravel: read by its name), Symfony's core types, a constraint, a field type of the application's
 * (VisitorsType, a select of its own), a submit and a reset, an option of its own, and a form event (not read).
 */
final class VisitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('state', SelectType::class, ['label' => 'visit.state', 'class' => \Omnibridge\Laravel\Tests\Fixtures\Enumerations\VisitState::class, 'placeholder' => '—'])
            ->add('guests', IntegerType::class, ['label' => 'visit.guests', 'empty_data' => '1', 'attr' => ['min' => 1]])
            ->add('note', TextType::class, ['required' => false, 'constraints' => [new Length(max: 5, maxMessage: 'Too long.')]])
            ->add('visitors', VisitorsType::class, ['label' => 'visit.visitors'])
            ->add('countries', CountryType::class, ['required' => false, 'multiple' => true])
            ->add('on', DateType::class, ['required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('confirmed', CheckboxType::class, ['required' => false]);
        if ($options['buttons']) {
            $builder->add('save', SubmitType::class, ['label' => 'visit.save'])->add('reset', ResetType::class);
        }
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'csrf_protection' => false, 'buttons' => true]);
        $resolver->setAllowedTypes('buttons', 'bool');
    }
}
