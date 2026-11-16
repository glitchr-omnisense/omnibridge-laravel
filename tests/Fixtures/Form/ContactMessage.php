<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Form;

use Symfony\Component\Validator\Constraints as Assert;

/** A site's contact message, as a Symfony site writes it (its rules as Symfony's attributes). */
final class ContactMessage
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    public ?string $phone = null;

    #[Assert\NotBlank]
    public ?string $message = null;

    public ?string $website = null;
}
