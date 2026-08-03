<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttles the public token endpoint, the one credential-stuffing target in the
 * mobile API.
 *
 * Two things the plain `throttle` middleware cannot do on its own:
 *
 * - **The key is email + IP, not IP.** Employees at a premise punch in over the
 *   same wifi or mobile NAT. An IP-keyed limiter would let one employee's bad
 *   shift-start attempts lock out everyone else at exactly the moment they all
 *   need to clock in — a worse outage than the attack it prevents.
 * - **A success forgets the failures.** ThrottleRequests counts every request, so
 *   an employee who mistypes twice and then signs in correctly would start the
 *   next shift two attempts from the limit. This mirrors the shape the web login
 *   already has through Laravel\Fortify\LoginRateLimiter.
 *
 * The per-IP ceiling that stops an attacker walking a list of emails past this
 * limiter is the api group's baseline limit, not this one.
 */
class ThrottleTokenIssuance extends ThrottleRequests
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     *
     * @throws ThrottleRequestsException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        $key = $this->throttleKey($request);

        $response = $this->handleRequest($request, $next, [
            (object) [
                'key' => $key,
                'maxAttempts' => self::MAX_ATTEMPTS,
                'decaySeconds' => self::DECAY_SECONDS,
                'afterCallback' => null,
                'responseCallback' => null,
            ],
        ]);

        if ($response->isSuccessful()) {
            $this->limiter->clear($key);

            // handleRequest() already stamped the headers from the counter as it
            // stood before the clear; restate them so the app is not told it has
            // fewer attempts left than it does.
            return $this->addHeaders($response, self::MAX_ATTEMPTS, self::MAX_ATTEMPTS);
        }

        return $response;
    }

    /**
     * A request whose `email` is missing or is not a string still gets a key —
     * validation has not run yet at this point, and an unkeyed request must not
     * escape the limiter.
     */
    private function throttleKey(Request $request): string
    {
        $email = $request->input('email');

        return 'api-token-issuance|'.Str::transliterate(
            Str::lower(is_string($email) ? $email : '').'|'.$request->ip()
        );
    }
}
