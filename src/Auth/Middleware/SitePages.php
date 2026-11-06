<?php

namespace Omnibridge\Laravel\Auth\Middleware;

use Base\Request\Rule\LaunchPage;
use Base\Request\Rule\MaintenancePage;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Omnibridge\Contract\Access;
use Omnibridge\Laravel\Http\Decisions;
use Omnibridge\Laravel\Http\RequestPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * omnibase's maintenance and launch pages on every page of the web group (Base\Request\Rule\{MaintenancePage,
 * LaunchPage}, step F5e of its plan, slice 2; SecuritySubscriber::onMaintenanceRequest() and onLaunchRequest()
 * under Symfony), each where its service is bound (Base\Service\MaintenanceProviderInterface,
 * LauncherInterface, with the parameters and the settings).
 */
final class SitePages
{
    public const MAINTENANCE = 'Base\Service\MaintenanceProviderInterface';
    public const LAUNCHER = 'Base\Service\LauncherInterface';
    public const PARAMETERS = 'Base\Service\ParameterBagInterface';
    public const SETTINGS = 'Base\Service\SettingBagInterface';

    public function __construct(private readonly Container $app)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->app->bound(self::PARAMETERS)) {
            $parameters = $this->app->make(self::PARAMETERS);
            $access = $this->app->make(Access::class);
            $granted = static fn (string $attribute): bool => $access->isGranted($attribute);
            $page = new RequestPage($request, $parameters);
            $decisions = [];
            if ($this->app->bound(self::MAINTENANCE)) {
                $decisions[] = (new MaintenancePage($this->app->make(self::MAINTENANCE), $parameters->get('base.site.maintenance.redirect_on_deny'), $parameters->get('base.site.index')))->decide($page, $granted);
            }
            if ($this->app->bound(self::LAUNCHER) && $this->app->bound(self::SETTINGS) && (!isset($decisions[0]) || !$decisions[0]->final)) {
                $redirect = (bool) $this->app->make(self::SETTINGS)->getScalar('base.settings.launchdate.redirect_on_deny');
                $decisions[] = (new LaunchPage($this->app->make(self::LAUNCHER), $redirect, $parameters->get('base.site.index')))->decide($page, $granted, $this->app->getLocale());
            }
            foreach ($decisions as $decision) {
                if (null !== $answer = Decisions::answer($this->app, $decision)) {
                    return $answer;
                }
            }
        }
        $response = $next($request);
        \assert($response instanceof Response);

        return $response;
    }
}
