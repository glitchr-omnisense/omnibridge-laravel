<?php

namespace Omnibridge\Laravel\Form;

/**
 * A form as omnibase's templates read one (Symfony's FormView: its vars, its
 * children by name), for the few forms its pages show on every page
 * (omnibase's FormProxy: the search bar, the newsletter), printed with the
 * markup of omnibase's form theme by FormRenderer. Only the view: what
 * answers its submission is the application's.
 *
 * @implements \ArrayAccess<string, FormView>
 * @implements \IteratorAggregate<string, FormView>
 */
final class FormView implements \ArrayAccess, \IteratorAggregate
{
    /** @var array<string, mixed> */
    public array $vars;

    /** @var array<string, FormView> */
    public array $children = [];

    public bool $rendered = false;

    /** @param array<string, mixed> $vars */
    public function __construct(string $name, array $vars = [], public readonly ?FormView $parent = null)
    {
        $full = null !== $parent ? $parent->vars['full_name'].'['.$name.']' : $name;
        // Symfony's ids: the full name with its brackets made underscores, the leading underscores and digits dropped (not allowed in an id).
        $id = null !== $parent ? $parent->vars['id'].'_'.$name : ltrim($name, '_0123456789');
        $this->vars = $vars + ['name' => $name, 'full_name' => $full, 'id' => $id, 'type' => 'text', 'value' => null, 'attr' => [], 'required' => false, 'label' => null];
    }

    /** @param array<string, mixed> $vars */
    public function add(string $name, array $vars = []): self
    {
        $this->children[$name] = new self($name, $vars, $this);

        return $this;
    }

    /** What omnibase's FormProxy hands: a form whose view is made on demand. */
    public function createView(): self
    {
        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->children[$offset]);
    }

    public function offsetGet(mixed $offset): FormView
    {
        return $this->children[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('A form view is read only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('A form view is read only.');
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->children);
    }
}
