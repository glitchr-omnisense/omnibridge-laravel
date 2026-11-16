<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** A site's contact form, written for Symfony as the sites write it (brieucvourch's App\Form\ContactType). */
final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'contact.form.name', 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'contact.form.email', 'attr' => ['autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'contact.form.phone', 'required' => false, 'attr' => ['autocomplete' => 'tel']])
            ->add('message', TextareaType::class, ['label' => 'contact.form.message', 'attr' => ['rows' => 6]])
            ->add('website', TextType::class, ['required' => false, 'label' => false, 'row_attr' => ['class' => 'mh-trap', 'aria-hidden' => 'true'], 'attr' => ['tabindex' => '-1', 'autocomplete' => 'off']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
            'translation_domain' => 'messages',
            'guard' => ['action' => 'contact'],
        ]);
    }
}
