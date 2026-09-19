<?php

namespace App\Services\Auth;

use Illuminate\Support\Arr;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;
use RuntimeException;

/**
 * Signs users in through 3AG Accounts over OpenID Connect.
 *
 * Claims come from the provider's userinfo endpoint rather than the ID token,
 * because that call is a direct back-channel request over TLS and needs no
 * signature check of its own.
 */
class ThreeAgProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array<int, string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * The separator the provider uses between scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Public clients cannot keep a secret, and PKCE costs us nothing here.
     *
     * @var bool
     */
    protected $usesPKCE = true;

    /**
     * The raw token endpoint response for the most recent exchange.
     *
     * @var array<string, mixed>
     */
    protected array $tokenResponse = [];

    /**
     * Get the configured driver.
     *
     * The Socialite facade is typed against the generic provider contract,
     * which knows nothing about ID tokens or the provider's logout endpoint.
     */
    public static function resolve(): self
    {
        $driver = Socialite::driver('3ag');

        if (! $driver instanceof self) {
            throw new RuntimeException('The [3ag] Socialite driver is not registered.');
        }

        return $driver;
    }

    /**
     * {@inheritdoc}
     *
     * Narrowed from the generic contract: mapUserToObject() below always
     * builds an OAuth 2 user, and the callback needs its raw claims to read
     * `email_verified`.
     */
    public function user(): User
    {
        $user = parent::user();

        if (! $user instanceof User) {
            throw new RuntimeException('The [3ag] driver returned an unexpected user type.');
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $user
     */
    protected function userInstance(array $response, array $user): User
    {
        // Socialite keeps only the access and refresh tokens, but the ID token
        // is what proves to the provider which session is ending when the user
        // signs out.
        $this->tokenResponse = $response;

        return parent::userInstance($response, $user);
    }

    /**
     * Get the ID token returned alongside the access token.
     */
    public function idToken(): ?string
    {
        return $this->tokenResponse['id_token'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoint('/oauth/authorize'), $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->endpoint('/oauth/token');
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoint('/oauth/userinfo'), [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'sub'),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
        ]);
    }

    /**
     * Get the URL where the user is sent to end their session at the provider.
     */
    public function logoutUrl(?string $idToken = null): string
    {
        return $this->endpoint('/oauth/logout').'?'.http_build_query(array_filter([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => config('app.url').'/',
        ]));
    }

    /**
     * Build an absolute URL to one of the provider's endpoints.
     */
    protected function endpoint(string $path): string
    {
        return rtrim(config('services.3ag.base_url'), '/').$path;
    }
}
