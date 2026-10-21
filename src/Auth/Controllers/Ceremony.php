<?php

namespace Omnibridge\Laravel\Auth\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Omniauth\Factor;
use Omniauth\Method\Challenge;
use Omniauth\Omniauth;
use Omnibridge\Contract\Tokens;
use Omnibridge\Laravel\Auth\Contexts;
use Omnibridge\Laravel\Auth\OmniauthGuard;
use Omnibridge\Laravel\Auth\Pages;
use Omnibridge\Laravel\Auth\Settings;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The steps of a sign-in that sign nobody in yet (Routes): the second
 * factor's page, a passkey's request options, the trip out to an identity
 * provider. The verifications themselves are Controllers\SignIn's.
 *
 * The second factor's page renders the application's template with the very
 * variables scheb/2fa's FormController gave it (twoFactorProvider,
 * availableTwoFactorProviders, checkPathUrl, authCodeParameterName,
 * displayTrustedOption, isCsrfProtectionEnabled, logoutPath...), the factors
 * under scheb's names ("email" for omniauth's "email-code"): the template
 * the Symfony site renders renders here unchanged. The code by e-mail is
 * sent when its page is first shown (as scheb/2fa sent it), and again once
 * it expired.
 */
final class Ceremony
{
    /** omniauth's factors under the names scheb/2fa gave its providers. */
    public const PROVIDER_NAMES = ['totp' => 'totp', 'email-code' => 'email', 'backup-code' => 'backup-code'];

    public function __construct(
        private readonly Omniauth $omniauth,
        private readonly Contexts $contexts,
        private readonly Settings $settings,
        private readonly Pages $pages,
        private readonly Auth $auth,
        private readonly Redirector $redirector,
        private readonly UrlGenerator $urls,
        private readonly ?Tokens $tokens = null,
    ) {
    }

    public function secondFactorPage(Request $request): Response
    {
        $guard = $this->auth->guard();
        $attempt = $guard instanceof OmniauthGuard ? $guard->pending() : null;
        if (null === $attempt) {
            $this->omniauth->abandon();

            return $this->redirector->route($this->settings->loginRoute);
        }
        $offered = $attempt->offered();
        $session = $request->session();
        $preferred = $request->query->get('preferProvider');
        if (\is_string($preferred) && null !== ($factor = Factor::tryFrom(array_flip(self::PROVIDER_NAMES)[$preferred] ?? $preferred)) && \in_array($factor, $offered, true)) {
            $session->put(SignIn::CHOSEN, $factor->value);
        }
        $chosen = Factor::tryFrom(Settings::string($session->get(SignIn::CHOSEN)));
        $factor = null !== $chosen && \in_array($chosen, $offered, true) ? $chosen : self::firstOf($offered);
        if (null === $factor) {
            throw new \LogicException('A second factor is asked, none is offered.');
        }
        $session->put(SignIn::CHOSEN, $factor->value);
        if (Factor::EmailCode === $factor && !\is_int($attempt->kept(Factor::EmailCode))) {
            $this->omniauth->begin(Factor::EmailCode, $this->contexts->of($request));
        }

        $error = $session->pull(SignIn::SECOND_FACTOR_ERROR);
        $second = $this->settings->secondFactor;
        $csrf = $this->settings->csrf($second);
        $providers = array_values(array_map(static fn (Factor $f) => self::PROVIDER_NAMES[$f->value] ?? $f->value, array_filter($offered, static fn (Factor $f) => Factor::BackupCode !== $f)));

        return $this->pages->render($request, Settings::string($second['template']), [
            'twoFactorProvider' => self::PROVIDER_NAMES[$factor->value] ?? $factor->value,
            'availableTwoFactorProviders' => $providers,
            'authenticationError' => \is_array($error) ? $error['key'] : null,
            'authenticationErrorData' => \is_array($error) ? $error['data'] : [],
            'displayTrustedOption' => [] !== $this->omniauth->methods()->ambient(),
            'authCodeParameterName' => Settings::string($second['code']),
            'trustedParameterName' => Settings::string($second['trusted']),
            'isCsrfProtectionEnabled' => null !== $csrf && null !== $this->tokens,
            'csrfParameterName' => $csrf['field'] ?? '_csrf_token',
            'csrfTokenId' => $csrf['intent'] ?? 'two_factor',
            'checkPathRoute' => Settings::string($second['check_route']),
            'checkPathUrl' => $this->urls->route(Settings::string($second['check_route'])),
            'logoutPath' => $this->logoutPath(),
        ]);
    }

    /** A passkey's request options, in the JSON web-auth/webauthn-symfony-bundle answered: {"status": "ok", "errorMessage": "", ...the options}. */
    public function passkeyOptions(Request $request): Response
    {
        $challenge = $this->omniauth->begin(Factor::Passkey, $this->contexts->of($request));
        if ($challenge->hasFailed() || Challenge::JSON !== $challenge->kind) {
            return new JsonResponse(['status' => 'failed', 'errorMessage' => (string) ($challenge->reason ?? $challenge->failure?->value)], Response::HTTP_BAD_REQUEST);
        }
        $options = json_decode((string) $challenge->payload, true, 32, \JSON_THROW_ON_ERROR);

        return new JsonResponse(['status' => 'ok', 'errorMessage' => ''] + (\is_array($options) ? $options : []), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    /** The trip out to an identity provider: its authorization address, the state, PKCE and nonce kept in the attempt. */
    public function oauthStart(Request $request, string $provider): Response
    {
        if (!$this->omniauth->methods()->has(Factor::OAuth)) {
            throw new NotFoundHttpException('No identity provider is served.');
        }
        $challenge = $this->omniauth->begin(Factor::OAuth, $this->contexts->of($request), ['provider' => $provider]);
        if ('unknown-provider' === $challenge->reason) {
            throw new NotFoundHttpException(\sprintf('The "%s" provider is not served.', $provider));
        }
        if ($challenge->hasFailed() || Challenge::REDIRECT !== $challenge->kind) {
            return $this->redirector->route($this->settings->loginRoute);
        }

        return $this->redirector->away((string) $challenge->payload);
    }

    /**
     * The factor the second factor's page shows first: the first offered that is not a backup code.
     *
     * @param list<Factor> $offered
     */
    public static function firstOf(array $offered): ?Factor
    {
        foreach ($offered as $factor) {
            if (Factor::BackupCode !== $factor) {
                return $factor;
            }
        }

        return $offered[0] ?? null;
    }

    /** The sign-out's address for this session (its token in it), as omnibase's SignOut makes it. */
    private function logoutPath(): string
    {
        $logout = $this->settings->logout;
        $csrf = $this->settings->csrf($logout);
        $parameters = null !== $csrf && null !== $this->tokens ? [$csrf['field'] => $this->tokens->csrf($csrf['intent'])] : [];

        return $this->urls->route(Settings::string($logout['page_route']), $parameters);
    }
}
