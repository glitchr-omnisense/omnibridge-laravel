<?php

namespace Omnibridge\Laravel\Tests;

use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint as Neutral;
use Omnibridge\Validation\Validator as PlainValidator;

final class ValidationTest extends TestCase
{
    /** @param list<Violation> $violations @return list<string> */
    private static function messages(array $violations): array
    {
        return array_map(static fn (Violation $v): string => ('' === $v->path ? '' : $v->path.': ').$v->message, $violations);
    }

    public function testEachKeptConstraintSaysWhatThePlainValidatorSays(): void
    {
        $this->app->setLocale('en');
        $validation = $this->app->make(Validation::class);
        foreach ([
            ['', [new Neutral\NotBlank()]],
            ['   ', [new Neutral\NotBlank()]],
            [null, [new Neutral\NotBlank()]],
            ['ab', [new Neutral\Length(min: 3)]],
            ['ringo@', [new Neutral\Email()]],
            ['ab1', [new Neutral\Regex('/^[a-z]+$/')]],
            ['x', [new Neutral\Choice(['a', 'b'])]],
            [6, [new Neutral\Range(min: 1, max: 5)]],
            ['ringo@example.com', [new Neutral\Email()]],
        ] as [$value, $constraints]) {
            self::assertSame(self::messages((new PlainValidator())->validate($value, $constraints)), self::messages($validation->validate($value, $constraints)));
        }
    }

    public function testLaravelsOwnRulesAndAnObjectsAttributes(): void
    {
        $this->app->setLocale('en');
        $validation = $this->app->make(Validation::class);
        self::assertCount(1, $validation->validate('abc', ['integer']));

        $record = new class {
            #[Neutral\NotBlank]
            public ?string $name = null;
        };
        self::assertSame(['name: This value should not be blank.'], self::messages($validation->validate($record)));
    }

    public function testSymfonysMessagesInTheVisitorsLanguage(): void
    {
        $this->app->setLocale('fr');
        $validation = new \Omnibridge\Laravel\Contract\LaravelValidation($this->app->make('validator'), $this->app->make(Translations::class));

        self::assertSame(['Cette valeur ne doit pas être vide.'], self::messages($validation->validate('', [new Neutral\NotBlank()])));
        self::assertSame(['Cette chaîne est trop courte. Elle doit contenir au minimum 3 caractères.'], self::messages($validation->validate('ab', [new Neutral\Length(min: 3)])));
    }
}
