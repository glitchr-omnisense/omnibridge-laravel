<?php

namespace Omnibridge\Laravel\Auth;

use Base\Auth\Policy\Access;
use Base\Auth\Policy\Admin;
use Base\Auth\Policy\Complaint;
use Base\Auth\Policy\Launch;
use Base\Auth\Policy\Maintenance;
use Base\Auth\Policy\Permission;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * omnibase's access policies as abilities of Laravel's gate (Gate::before; omnisense/omnibase 5.0, step F5e
 * of its plan), the decisions its voters take under Symfony: BACKEND on the back office's pages (Admin),
 * the permission tags (Permission), COMPLAINT_READ / COMPLAINT_HANDLE (Complaint, on Roles' hierarchy),
 * and the access restriction's attributes (Access, Maintenance, Launch) where the parameters, the localizer,
 * the maintenance and the launcher are bound - one firewall, `main`, as the Symfony site's pages are.
 */
final class Policies
{
    /** The back office's routes (their names) and the profiler's; Symfony's AdvancedRouter isAdmin()/isProfiler(). */
    public const BACK_OFFICE = ['admin', 'admin_*', 'backoffice*', '_profiler*', '_wdt*'];

    private ?Access $access = null;

    public function __construct(private readonly Container $app)
    {
    }

    /** @param list<mixed> $arguments */
    public function decide(?Authenticatable $user, string $ability, array $arguments = []): ?bool
    {
        $account = $user instanceof AccountUser ? $user->account : $user;
        $subject = $arguments[0] ?? null;

        if ((new Admin())->supports($ability, fn (): bool => $this->request()?->routeIs(...self::BACK_OFFICE) ?? false)) {
            return (new Admin())->decide($account);
        }
        $complaint = new Complaint($this->app->make(Roles::class));
        if ($complaint->supports($ability, $subject)) {
            return $complaint->decide($ability, $subject, $account, fn (string $tag): bool => $this->app->make(Gate::class)->forUser($user)->allows($tag));
        }
        if (\in_array($ability, Access::ATTRIBUTES, true)) {
            if (null === $access = $this->access()) {
                return null;
            }

            return match ($ability) {
                Maintenance::ATTRIBUTE => (new Maintenance($this->app->make('Base\Service\MaintenanceProviderInterface'), $access))->allows($subject ?? $this->request()?->getUri()),
                Launch::ATTRIBUTE => (new Launch($this->app->make('Base\Service\LauncherInterface'), $access))->allows($subject ?? $this->request()?->getUri()),
                default => $access->decide($ability, $subject ?? (Access::EXCEPTION_ACCESS === $ability ? $this->request()?->getUri() : null)),
            };
        }
        if ((new Permission())->supports($ability)) {
            return (new Permission())->decide($ability, $account);
        }

        return null;
    }

    /** The restriction's policy, made once, where its services are bound. */
    public function access(): ?Access
    {
        foreach (['Base\Service\ParameterBagInterface', 'Base\Service\LocalizerInterface', 'Base\Service\MaintenanceProviderInterface', 'Base\Service\LauncherInterface'] as $service) {
            if (!$this->app->bound($service)) {
                return null;
            }
        }

        // A host whose configuration says nothing of the restriction (omnibase's `base.access_restriction`) has none.
        if (null === $this->app->make('Base\Service\ParameterBagInterface')->get('base.access_restriction.public_access')) {
            return null;
        }

        return $this->access ??= new Access(
            $this->app->make('Base\Service\ParameterBagInterface'),
            $this->app->make('Base\Service\LocalizerInterface'),
            static fn (): string => 'main',
            fn (): string => $this->app->bound(\Omnibridge\Contract\Environment::class) ? $this->app->make(\Omnibridge\Contract\Environment::class)->name() : (string) $this->app->environment(),
        );
    }

    private function request(): ?Request
    {
        return $this->app->bound('request') ? $this->app->make('request') : null;
    }
}
