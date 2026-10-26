<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Base\Database\Attribute\DiscriminatorEntry;
use Doctrine\ORM\Mapping as ORM;

/**
 * An application's own App\Entity\User over omnibase's Base\Entity\User: the
 * omnibase harness's (docker/app/src/Entity/User.php of omnisense/omnibase),
 * whose Symfony metadata the core records in
 * tests/Bridge/Doctrine/fixtures/doctrine-metadata.json - the class that
 * overrides a Base\ one.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'userProfile')]
#[DiscriminatorEntry(value: 'user')]
class User extends \Base\Entity\User
{
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    protected $username;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->username ?? parent::__toString());
    }
}
