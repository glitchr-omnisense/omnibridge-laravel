<?php

namespace Omnibridge\Laravel\Contract;

use Illuminate\Contracts\Validation\Factory;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint\Constraint;
use Omnibridge\Validation\Validator as PlainValidator;

/**
 * Validation under Laravel: the kept constraints by omnibridge's Validator, so that their verdict
 * and message are those of every host - Laravel's `required` and `email` do not say the same as
 * Symfony's NotBlank and Email ("   ", false, an address without a dot) -, and Laravel's validator
 * for its own rules (a string or a Rule object among the constraints). An object is checked against
 * the kept constraints it declares as attributes. Symfony's messages come from the `validators`
 * catalogue (Translations), the same words in French as the Symfony site's. Unique asks $exists.
 */
final class LaravelValidation implements Validation
{
    /** @var (\Closure(string, array<string, mixed>, object|null): bool)|null */
    private readonly ?\Closure $exists;

    public function __construct(private readonly Factory $factory, private readonly ?Translations $translations = null, ?callable $exists = null)
    {
        $this->exists = null !== $exists ? $exists(...) : null;
    }

    public function validate(mixed $value, array $constraints = [], ?array $groups = null): array
    {
        $plain = new PlainValidator($this->exists, null !== $this->translations ? $this->translate(...) : null);
        $violations = [];
        if (\is_object($value)) {
            array_push($violations, ...$plain->validate($value, [], $groups));
        }

        $kept = array_values(array_filter($constraints, static fn (mixed $constraint): bool => $constraint instanceof Constraint));
        array_push($violations, ...$plain->only($value, $kept, $groups));

        $rules = array_values(array_filter($constraints, static fn (mixed $constraint): bool => !$constraint instanceof Constraint));
        if ([] !== $rules) {
            $validator = $this->factory->make(['value' => $value], ['value' => $rules]);
            foreach ($validator->fails() ? $validator->errors()->get('value') : [] as $message) {
                $violations[] = new Violation('', $message);
            }
        }

        return $violations;
    }

    /** @param array<string, mixed> $parameters */
    private function translate(string $template, array $parameters): string
    {
        $count = $parameters['%count%'] ?? null;
        unset($parameters['%count%']);
        $forms = explode('|', $this->translations->trans($template, [], 'validators'));
        // A message of omnibase's catalogues is ICU's ("{value}" est déjà utilisé.), left as it is without its
        // arguments: a validator's {{ value }} is its {value} too, its value as omnibase's own validators give it
        // (unquoted). strtr() tries the longer {{ value }} first where both could match.
        $replacements = array_map(strval(...), $parameters);
        foreach ($parameters as $name => $value) {
            if (preg_match('/^\{\{ ?(\w+) ?\}\}$/', (string) $name, $m)) {
                $value = (string) $value;
                $replacements['{'.$m[1].'}'] ??= \strlen($value) >= 2 && '"' === $value[0] && '"' === $value[-1] ? substr($value, 1, -1) : $value;
            }
        }

        return strtr(1 === $count || 1 === \count($forms) ? $forms[0] : $forms[1], $replacements);
    }
}
