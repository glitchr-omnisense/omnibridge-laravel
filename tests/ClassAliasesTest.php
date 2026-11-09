<?php

namespace Omnibridge\Laravel\Tests;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * omnibase's class convention on Laravel from the start (master plan §5, the Base/App precedence): App\Enum\X,
 * App\Entity\X... is the Base\ class an application does not define, as omnibase's BaseBundle answers it under Symfony
 * - not only once Doctrine's entity manager has been built (omnibridge/doctrine's Mapping registered it until then, so
 * a page that read no entity found no App\Enum\CommentState: omnibridge/parity's /aliases).
 */
final class ClassAliasesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAnAppClassIsTheBaseOneBeforeAnyEntityManager(): void
    {
        $app = $this->app;
        $this->assertNotNull($app);
        $this->assertFalse($app->resolved(EntityManagerInterface::class), 'no entity manager built');
        $class = 'App\\Enum\\CommentState';
        $this->assertTrue(enum_exists($class));
        // An enum has no subclass: is_a() is the very enum, under another name.
        $this->assertTrue(is_a($class, \Base\Enum\CommentState::class, true), 'an alias of omnibase\'s, not an enum of its own');
        $this->assertFalse(class_exists('App\\Enum\\NoSuchEnum'), 'nothing for a class omnibase does not have');
    }
}
