<?php

namespace Omnibridge\Laravel\Tests;

use Base\Contract\Runtime;
use Base\Service\HotParameters;
use Base\Service\ParameterBagInterface;
use Omnibridge\Laravel\Parameters\ConfigParameters;

/**
 * Runtime::parameters(): Laravel's config, with the settings the back office
 * links to a parameter laid over it (omnibase's HotParameters) - read in the
 * database once, by presence: a setting stored as false wins over the
 * config's true.
 */
final class ParametersTest extends TestCase
{
    private function withSettings(?bool $hot): ParameterBagInterface
    {
        config()->set('base.user', ['register' => true, 'delay' => 30]);
        config()->set('base.site', ['title' => 'Config']);
        config()->set('omnibridge.parameters.hot', $hot);
        $this->settings([
            'base.settings.register' => ['base.user.register', ['fr-FR' => false, 'en-GB' => true]],
            'base.settings.title' => [null, ['fr-FR' => 'Les Ringo Stars']],
        ]);

        return Runtime::parameters();
    }

    public function testTheConfigIsReadByItsDottedPath(): void
    {
        $parameters = Runtime::parameters();
        $this->assertInstanceOf(HotParameters::class, $parameters);
        $this->assertSame($parameters, $this->app->make(ParameterBagInterface::class));
        $this->assertSame('fr', $parameters->get('app.locale'));
        $this->assertTrue($parameters->has('app.locale'));
        $this->assertFalse($parameters->has('app.nowhere'));
        $this->assertSame('fr', $parameters->all()['app.locale']);
    }

    public function testAFalseSettingWinsOverTheConfigsTrue(): void
    {
        $parameters = $this->withSettings(true);

        $this->assertFalse($parameters->get('base.user.register'), 'the default language\'s value');
        $this->assertTrue($parameters->has('base.user.register'));
        $this->assertSame(['register' => false, 'delay' => 30], $parameters->get('base.user'));
        $this->assertSame(30, $parameters->get('base.user.delay'));
        $this->assertSame('Config', $parameters->get('base.site.title'), 'a setting linked to no parameter is no parameter');
        $this->assertTrue($parameters->get('base.user.register', useHotBag: false));
        $this->assertTrue($parameters->isReady());
    }

    public function testWithoutTheOverlayTheConfigAlone(): void
    {
        $this->assertTrue($this->withSettings(false)->get('base.user.register'));
    }

    public function testTheConfigItselfTurnsTheOverlayOn(): void
    {
        config()->set('base.parameter_bag.use_hot_bag', true);

        $this->assertFalse($this->withSettings(null)->get('base.user.register'));
    }

    public function testABagGivenIsLaidOverAll(): void
    {
        $parameters = $this->withSettings(true);

        $this->assertSame('given', $parameters->get('base.user.register', ['base.user.register' => 'given']));
        $this->assertFalse($parameters->get('base.user.register', ['base.user.register' => null]), 'null is no value');
        $this->assertSame(['register' => false, 'delay' => 30, 'new' => 1], $parameters->get('base.user', ['base.user.new' => 1]));
    }

    public function testADatabaseThatDoesNotAnswerLeavesTheConfig(): void
    {
        config()->set('base.user.register', true);
        config()->set('omnibridge.parameters.hot', true);

        $parameters = Runtime::parameters();
        $this->assertTrue($parameters->get('base.user.register'));
        $this->assertFalse($parameters->isReady());
    }

    public function testSetWritesTheConfigOrTheBagGiven(): void
    {
        $parameters = new ConfigParameters(config());
        $parameters->set('base.user.delay', 60);
        $this->assertSame(60, config('base.user.delay'));

        $bag = [];
        $parameters->set('base.user.delay', 90, $bag);
        $this->assertSame(['base' => ['user' => ['delay' => 90]]], $bag);
        $this->assertSame(60, config('base.user.delay'));
    }
}
