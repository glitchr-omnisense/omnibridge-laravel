<?php

namespace Omnibridge\Laravel\Auth\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Omniauth\Factor;
use Omniauth\Failure;
use Omniauth\Omniauth;
use Omniauth\Proof;
use Omniauth\Result;
use Omniauth\Role;
use Omniauth\Status;
use Omnibridge\Contract\Tokens;
use Omnibridge\Laravel\Auth\Contexts;
use Omnibridge\Laravel\Auth\OmniauthGuard;
use Omnibridge\Laravel\Auth\Pages;
use Omnibridge\Laravel\Auth\Refusal;
use Omnibridge\Laravel\Auth\Settings;
use Omnibridge\Laravel\Form\FormView;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Every way omniauth signs in, under Laravel (plan "omniauth", §3.2; what
 * omnibridge/symfony's OmniauthAuthenticator does under Symfony): the
 * password form (its page too), the sign-in link, a passkey, the second
 * factor's form, the return from an identity provider - each request turned
 * into omniauth's Proof and Context, the forms' CSRF token checked through
 * omnibridge's Tokens contract, Omniauth::verify() asked, and its Result
 * answered:
 *
 * - Authenticated: the guard opens the session (OmniauthGuard::enter(): the
 *   id renewed, the remember-me cookie when omniauth says so), the
 *   trusted_device cookie when it gave one; the page asked for, else the
 *   default; a passkey's answer in JSON.
 * - SecondFactorRequired: the attempt stays in the session (the guard has no
 *   user, pending() has it) and the second factor's page follows.
 * - Failed, IdentityUnknown: a Refusal for the page the visitor came from
 *   (`error`, the messages of config/omniauth.php); a passkey's in JSON, as
 *   web-auth/webauthn-symfony-bundle answered ({"status": "failed", ...}); a
 *   refused sign-in link is no link, the page answers as for none; an
 *   unknown identity waits for the sign-up (PENDING_IDENTITY).
 *
 * And the sign-out: CSRF-checked as Security's logout with enable_csrf (a
 * request without the session's token is refused, 403), its page asking to
 * confirm by a link that carries one.
 */
final class SignIn
{
    /** Where the second factor's page keeps the factor the visitor chose (scheb/2fa's "preferProvider"). */
    public const CHOSEN = 'omnibridge.auth.factor';
    /** Where a refused second factor's error waits for its page. */
    public const SECOND_FACTOR_ERROR = 'omnibridge.auth.second_factor_error';
    /** Where an unknown identity waits for the sign-up (omniauth's PendingIdentity, as an array). */
    public const PENDING_IDENTITY = 'omnibridge.auth.pending_identity';
    /** Where the last refusal waits for the sign-in page (Refusal, as an array). */
    public const LAST_ERROR = 'omnibridge.auth.last_error';
    /** The identifier last typed, shown again by the sign-in page. */
    public const LAST_USERNAME = 'omnibridge.auth.last_username';
    /** A sign-in link was asked: the page says one was sent. */
    public const LINK_SENT = 'omnibridge.auth.link_sent';

    public function __construct(
        private readonly Omniauth $omniauth,
        private readonly Contexts $contexts,
        private readonly Settings $settings,
        private readonly Pages $pages,
        private readonly Auth $auth,
        private readonly Cookies $cookies,
        private readonly Redirector $redirector,
        private readonly UrlGenerator $urls,
        private readonly ?Tokens $tokens = null,
    ) {
    }

    /** The sign-in page (GET), and the password posted to it. */
    public function login(Request $request): Response
    {
        return $request->isMethod('POST') ? $this->password($request) : $this->page($request);
    }

    /** The sign-in page: its form, the last identifier typed, the error left by a refused attempt. */
    public function page(Request $request): Response
    {
        if ($this->guard()->check()) {
            return $this->redirector->intended($this->defaultTarget());
        }
        $session = $request->session();
        $last = Settings::string($session->get(self::LAST_USERNAME));
        $error = Refusal::fromArray($session->pull(self::LAST_ERROR));
        $linkSent = (bool) $session->pull(self::LINK_SENT, false);

        return $this->pages->render($request, Settings::string($this->settings->password['template']), [
            'form' => $this->form($request, $last),
            'error' => $error,
            'last_username' => $last,
            'identifier' => $last,
            'link_sent' => $linkSent,
        ]);
    }

    /** The password form posted: omniauth's password factor. */
    public function password(Request $request): Response
    {
        $sign = $this->settings->password;
        $fields = self::fields($request, Settings::string($sign['form'] ?? null));
        $request->session()->put(self::LAST_USERNAME, Settings::string($fields[Settings::string($sign['identifier'])] ?? null));
        if (null !== ($refusal = $this->checkCsrf(Factor::Password, $this->settings->csrf($sign), $fields))) {
            return $this->refused($request, Factor::Password, $refusal);
        }
        $proof = new Proof([
            'identifier' => Settings::string($fields[Settings::string($sign['identifier'])] ?? null),
            'password' => Settings::string($fields[Settings::string($sign['password'])] ?? null),
            'remember' => filter_var($fields[Settings::string($sign['remember'])] ?? false, \FILTER_VALIDATE_BOOL),
        ]);

        return $this->outcome($request, Factor::Password, $this->omniauth->verify(Factor::Password, $this->contexts->of($request), $proof));
    }

    /** The sign-in link's page: with a token, the link signs in; without one (or a refused one), the page asks for an address. */
    public function link(Request $request, ?string $token = null): Response
    {
        $link = $this->settings->link ?? throw new \LogicException('The sign-in link is off (config/omniauth.php: link).');
        if (null !== $token && '' !== $token && $this->omniauth->methods()->has(Factor::Link)) {
            $result = $this->omniauth->verify(Factor::Link, $this->contexts->of($request), ['token' => $token]);
            if ($result->isAuthenticated() || $result->needsSecondFactor()) {
                return $this->outcome($request, Factor::Link, $result);
            }
            // A refused link is no link: the page answers as for none (it asks for an address).
            $request->attributes->set(Settings::string($link['parameter']), null);
        }
        if ($this->guard()->check()) {
            return $this->redirector->intended($this->defaultTarget());
        }
        $session = $request->session();

        return $this->pages->render($request, Settings::string($link['template']), [
            'form' => $this->linkForm($request),
            'error' => Refusal::fromArray($session->pull(self::LAST_ERROR)),
            'link_sent' => (bool) $session->pull(self::LINK_SENT, false),
            'token' => null,
        ]);
    }

    /** An address posted: a link sent to it when it has an account - the same answer either way. */
    public function requestLink(Request $request): Response
    {
        $link = $this->settings->link ?? throw new \LogicException('The sign-in link is off (config/omniauth.php: link).');
        $fields = self::fields($request, Settings::string($link['form']));
        $route = Settings::string($link['route']);
        if (null !== ($refusal = $this->checkCsrf(Factor::Link, $this->settings->csrf($link), $fields))) {
            $request->session()->put(self::LAST_ERROR, $refusal->toArray());

            return $this->redirector->route($route);
        }
        $challenge = $this->omniauth->begin(Factor::Link, $this->contexts->of($request), ['identifier' => Settings::string($fields[Settings::string($link['email'])] ?? null)]);
        if ($challenge->hasFailed()) {
            $request->session()->put(self::LAST_ERROR, Refusal::of(Factor::Link, $challenge->failure, $challenge->reason, $this->settings->messages)->toArray());
        } else {
            $request->session()->put(self::LINK_SENT, true);
        }

        return $this->redirector->route($route);
    }

    /** A passkey's assertion, as the browser's credential.toJSON() posts it. */
    public function passkey(Request $request): Response
    {
        $passkey = $this->settings->passkey ?? throw new \LogicException('Passkeys are off (config/omniauth.php: passkey).');
        $json = (string) $request->getContent();
        $data = json_decode($json, true);
        $proof = new Proof(['json' => $json, 'remember' => \is_array($data) && \array_key_exists('remember', $data) ? (bool) $data['remember'] : (bool) $passkey['remember']]);

        return $this->outcome($request, Factor::Passkey, $this->omniauth->verify(Factor::Passkey, $this->contexts->of($request), $proof));
    }

    /** The second factor's code posted (a GET of the check: back to its page). */
    public function secondFactorCheck(Request $request): Response
    {
        $second = $this->settings->secondFactor;
        $form = Settings::string($second['form_route']);
        if (!$request->isMethod('POST')) {
            return $this->redirector->route(null !== $this->guard()->pending() ? $form : $this->settings->loginRoute);
        }
        $pending = $this->guard()->pending();
        if (null === $pending) {
            $this->omniauth->abandon();
            $request->session()->put(self::LAST_ERROR, Refusal::of(Factor::Totp, Failure::NoAttempt, null, $this->settings->messages)->toArray());

            return $this->redirector->route($this->settings->loginRoute);
        }
        $factor = $this->secondFactor($request, $pending->offered());
        if (null !== ($refusal = $this->checkCsrf($factor, $this->settings->csrf($second), $request->request->all()))) {
            return $this->refused($request, $factor, $refusal);
        }
        $proof = new Proof([
            'code' => Settings::string($request->request->get(Settings::string($second['code']))),
            'trust' => filter_var($request->request->get(Settings::string($second['trusted']), false), \FILTER_VALIDATE_BOOL),
        ]);

        return $this->outcome($request, $factor, $this->omniauth->verify($factor, $this->contexts->of($request), $proof));
    }

    /** The return from an identity provider: its code and state (or its error). */
    public function oauthCheck(Request $request, string $provider): Response
    {
        if (!$this->omniauth->methods()->has(Factor::OAuth)) {
            throw new \LogicException('Identity providers are off (config/omniauth.php: oauth).');
        }
        $query = array_map(Settings::string(...), array_intersect_key($request->query->all(), array_flip(['code', 'state', 'error'])));

        return $this->outcome($request, Factor::OAuth, $this->omniauth->verify(Factor::OAuth, $this->contexts->of($request), new Proof(['provider' => $provider] + $query)));
    }

    /**
     * The sign-out's page: with the session's token, straight to the
     * sign-out; without it, a page asking to confirm by a link that carries
     * one (omnibase's SecurityController::Logout). Signed out already: back
     * to the page the visitor came from.
     */
    public function logoutPage(Request $request): Response
    {
        $logout = $this->settings->logout;
        $csrf = $this->settings->csrf($logout);
        $field = $csrf['field'] ?? '_csrf_token';
        $returnUrl = $this->referrer($request) ?? '/';
        if (!$this->guard()->check()) {
            return new RedirectResponse($returnUrl);
        }
        $token = $request->query->get($field);
        if (null === $csrf || null === $this->tokens || $this->tokens->isValid($csrf['intent'], \is_string($token) ? $token : null)) {
            return $this->redirector->route(Settings::string($logout['route']), \is_string($token) ? [$field => $token] : []);
        }

        return $this->pages->render($request, Settings::string($logout['template']), [
            'logoutPath' => $this->urls->route(Settings::string($logout['route']), [$field => $this->tokens->csrf($csrf['intent'])]),
            'returnUrl' => $returnUrl,
        ]);
    }

    /** The sign-out itself: the session's token required (as Security's logout with enable_csrf: 403 without it). */
    public function logout(Request $request): Response
    {
        $logout = $this->settings->logout;
        $csrf = $this->settings->csrf($logout);
        if (null !== $csrf && null !== $this->tokens) {
            $token = $request->request->get($csrf['field']) ?? $request->query->get($csrf['field']);
            if (!$this->tokens->isValid($csrf['intent'], \is_string($token) ? $token : null)) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
        }
        $this->guard()->logout();
        $target = Settings::string($logout['target']) ?: '/';

        return str_starts_with($target, '/') ? new RedirectResponse($target) : $this->redirector->route($target);
    }

    private function outcome(Request $request, Factor $factor, Result $result): Response
    {
        $session = $request->session();
        if (Status::IdentityUnknown === $result->status) {
            $identity = $result->identity;
            if (null !== $identity) {
                $session->put(self::PENDING_IDENTITY, ['provider' => $identity->provider, 'subject' => $identity->subject, 'email' => $identity->email, 'emailVerified' => $identity->emailVerified, 'name' => $identity->name]);
            }
            $signUp = Settings::string($this->settings->oauth['sign_up_route'] ?? null);
            if ('' !== $signUp) {
                return $this->redirector->route($signUp);
            }

            return $this->refused($request, $factor, Refusal::of($factor, Failure::BadCredentials, 'identity-unknown', $this->settings->messages));
        }
        if (!$result->isAuthenticated() && !$result->needsSecondFactor()) {
            return $this->refused($request, $factor, Refusal::of($factor, $result->failure ?? Failure::BadCredentials, $result->reason, $this->settings->messages));
        }

        $session->forget([self::LAST_ERROR, self::SECOND_FACTOR_ERROR, self::CHOSEN]);
        if ($result->needsSecondFactor()) {
            $url = $this->urls->route(Settings::string($this->settings->secondFactor['form_route']));

            return Factor::Passkey === $factor ? self::json(true, '', ['redirect' => $url]) : new RedirectResponse($url);
        }

        $this->guard()->enter($result);
        if (null !== $result->trustDevice && null !== ($device = $this->settings->trustedDevice)) {
            $this->cookies->queue(Cookie::create(Settings::string($device['cookie']), $result->trustDevice, time() + Settings::int($device['lifetime'] ?? null, 2592000), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX));
        }

        return Factor::Passkey === $factor ? self::json(true) : $this->target($request);
    }

    private function refused(Request $request, Factor $factor, Refusal $refusal): Response
    {
        $session = $request->session();
        if (Factor::Passkey === $factor) {
            return self::json(false, strtr($refusal->messageKey, array_map(Settings::string(...), $refusal->messageData)), [], Response::HTTP_UNAUTHORIZED);
        }
        if (Factor::Link === $factor) {
            // A refused link is no link: its page, as for none.
            return $this->link($request);
        }
        if (Role::Second === $factor->role()) {
            if (\in_array($refusal->failure, [Failure::Expired, Failure::NoAttempt], true) || null === $this->guard()->pending()) {
                // The attempt is gone: back to the first factor.
                $this->omniauth->abandon();
                $session->put(self::LAST_ERROR, $refusal->toArray());

                return $this->redirector->route($this->settings->loginRoute);
            }
            $session->put(self::SECOND_FACTOR_ERROR, ['key' => $refusal->messageKey, 'data' => $refusal->messageData]);

            return $this->redirector->route(Settings::string($this->settings->secondFactor['form_route']));
        }
        $session->put(self::LAST_ERROR, $refusal->toArray());
        $current = (string) $request->route()->getName();
        $route = Factor::Password === $factor && '' !== $current ? $current : $this->settings->loginRoute;

        return $this->redirector->route($route);
    }

    /**
     * @param array{field: string, intent: string}|null $csrf
     * @param array<string, mixed>                       $fields
     */
    private function checkCsrf(Factor $factor, ?array $csrf, array $fields): ?Refusal
    {
        if (null === $csrf || null === $this->tokens) {
            return null;
        }
        $token = $fields[$csrf['field']] ?? null;
        if ($this->tokens->isValid($csrf['intent'], \is_string($token) ? $token : null)) {
            return null;
        }

        return Refusal::of($factor, Failure::Refused, 'csrf', $this->settings->messages, 'csrf');
    }

    /**
     * The second factor a code is for: the one chosen on the page, else the
     * first offered; a backup code when the code is not shaped as the chosen
     * factor's (scheb/2fa took a backup code in any form).
     *
     * @param list<Factor> $offered
     */
    private function secondFactor(Request $request, array $offered): Factor
    {
        $chosen = Factor::tryFrom(Settings::string($request->session()->get(self::CHOSEN)));
        $factor = null !== $chosen && \in_array($chosen, $offered, true) ? $chosen : (Ceremony::firstOf($offered) ?? Factor::Totp);
        $code = preg_replace('~\s+~', '', Settings::string($request->request->get(Settings::string($this->settings->secondFactor['code'])))) ?? '';
        $digits = $this->settings->digits()[$factor->value] ?? 6;
        if (Factor::BackupCode !== $factor && \in_array(Factor::BackupCode, $offered, true) && '' !== $code && !preg_match('~^\d{'.$digits.'}$~', $code)) {
            return Factor::BackupCode;
        }

        return $factor;
    }

    /** Where the visitor goes once signed in: the page asked (a posted target path of this site, then the one Laravel saved), else the default. */
    private function target(Request $request): Response
    {
        $parameter = Settings::string($this->settings->password['target_path_parameter'] ?? null) ?: '_target_path';
        $posted = $request->request->get($parameter) ?? $request->query->get($parameter);
        if (\is_string($posted) && self::isLocal($posted, $request)) {
            return new RedirectResponse($posted);
        }

        return $this->redirector->intended($this->defaultTarget());
    }

    private function defaultTarget(): string
    {
        return Settings::string($this->settings->password['default_target_path'] ?? null) ?: '/';
    }

    /** The sign-in form as omnibase's templates read it: its fields under the form's name (or at the top of the request), its token. */
    private function form(Request $request, string $last): FormView
    {
        $sign = $this->settings->password;
        $name = Settings::string($sign['form'] ?? null);
        $csrf = $this->settings->csrf($sign);
        // As omnibase's SecurityLoginType renders it under Symfony: no action (the page's own address), the
        // identifier's value printed even empty, "remember me" ticked, the token's field under its Stimulus controller.
        $view = new FormView($name, [
            'method' => 'post',
            'action' => null,
            // The link's page may be another host's (the Symfony site's security_loginWithToken, when the link is off here).
            'allow_login_token' => (bool) ($sign['allow_login_token'] ?? false),
        ]);
        $view->add(Settings::string($sign['identifier']), ['type' => 'text', 'value' => $last, 'required' => true, 'attr' => ['value' => $last]]);
        $view->add(Settings::string($sign['password']), ['type' => 'password', 'required' => true]);
        $view->add(Settings::string($sign['remember']), ['type' => 'checkbox', 'value' => '1', 'checked' => true]);
        if (null !== $csrf && null !== $this->tokens) {
            $view->add($csrf['field'], ['type' => 'hidden', 'value' => $this->tokens->csrf($csrf['intent']), 'attr' => ['data-controller' => 'csrf-protection']]);
        }

        return self::flat($view, $name);
    }

    private function linkForm(Request $request): FormView
    {
        $link = $this->settings->link ?? [];
        $name = Settings::string($link['form'] ?? null);
        $csrf = $this->settings->csrf($link);
        $view = new FormView($name, ['method' => 'post', 'action' => $request->getPathInfo()]);
        $view->add(Settings::string($link['email'] ?? 'email'), ['type' => 'email', 'required' => true]);
        if (null !== $csrf && null !== $this->tokens) {
            $view->add($csrf['field'], ['type' => 'hidden', 'value' => $this->tokens->csrf($csrf['intent'])]);
        }

        return self::flat($view, $name);
    }

    /**
     * A form's fields as posted: under the form's name, or at the top of the request when it has none.
     *
     * @return array<string, mixed>
     */
    private static function fields(Request $request, string $form): array
    {
        $all = $request->request->all();
        if ('' === $form) {
            return Settings::stringKeys($all);
        }
        $fields = $all[$form] ?? null;

        return \is_array($fields) ? Settings::stringKeys($fields) : [];
    }

    /** A form without a name has its fields at the top of the request: each one's full name is its own. */
    private static function flat(FormView $view, string $name): FormView
    {
        if ('' === $name) {
            foreach ($view->children as $child) {
                $child->vars['full_name'] = $child->vars['name'];
                $child->vars['id'] = $child->vars['name'];
            }
        }

        return $view;
    }

    private function referrer(Request $request): ?string
    {
        $referrer = (string) $request->headers->get('referer');

        return '' !== $referrer && self::isLocal($referrer, $request) && !str_contains($referrer, $request->getPathInfo()) ? $referrer : null;
    }

    private static function isLocal(string $url, Request $request): bool
    {
        if ('' === $url) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//') && !str_starts_with($url, '/\\');
        }
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && 0 === strcasecmp($host, $request->getHost()) && \in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /** @param array<string, mixed> $extra */
    private static function json(bool $ok, string $error = '', array $extra = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['status' => $ok ? 'ok' : 'failed', 'errorMessage' => $error] + $extra, $status);
    }

    private function guard(): OmniauthGuard
    {
        $guard = $this->auth->guard();

        return $guard instanceof OmniauthGuard ? $guard : throw new \LogicException(\sprintf('The default guard is no %s: config/auth.php, driver "omniauth".', OmniauthGuard::class));
    }
}
