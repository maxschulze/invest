<?php

namespace Helio\Invest\Utility;

use Firebase\JWT\JWT;

use Helio\Invest\Middleware\CustomApiTokenForUser;
use Helio\Invest\Middleware\LoadUserFromJwt;
use Helio\Invest\Middleware\TokenAttributeToCookie;
use Helio\Invest\Middleware\ReAuthenticate;
use Helio\Invest\Model\User;
use Slim\App;
use Slim\Http\StatusCode;
use Tuupola\Base62;

use Slim\Http\Request;
use Slim\Http\Response;
use Tuupola\Middleware\CorsMiddleware;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\JwtAuthentication\RequestPathRule;

class JwtUtility
{


    /**
     * @param App $app
     *
     * NOTE: Middlewares are processed as a FILO stack, so beware their order
     */
    public static function addMiddleware(App $app): void
    {

        $container = $app->getContainer();

        $container['jwt'] = function () {
            return [];
        };
        $container['user'] = function () {
            return null;
        };

        $app->add(new ReAuthenticate());

        $app->add(new LoadUserFromJwt());

        $app->add(new JwtAuthentication([
            'logger' => $container['logger'],
            'secret' => ServerUtility::get('JWT_SECRET'),
            'rules' => [
                new RequestPathRule([
                    'path' => '/(app|api)'
                ]),
                new RequestMethodRule(['passthrough' => ['OPTIONS']]),
            ],
            'before' => function (Request $request, array $arguments) use ($container) {
                $container['jwt'] = $arguments['decoded'];

            },
            'error' => function (Response $response, array $arguments) {
                $data['status'] = 'error';
                $data['message'] = $arguments['message'];

                if (strpos('application/json', ServerUtility::get('HTTP_ACCEPT', '')) !== false) {

                    return CookieUtility::deleteCookie($response
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)), 'token');
                }

                return CookieUtility::deleteCookie($response
                    ->withHeader('Location', '/')
                    ->withStatus(StatusCode::HTTP_SEE_OTHER), 'token');
            }
        ]));

        $app->add(new CustomApiTokenForUser());

        $app->add(new TokenAttributeToCookie());

        $app->add(new CorsMiddleware([
            'logger' => $container['logger'],
            'origin' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'headers.allow' => ['Authorization', 'If-Match', 'If-Unmodified-Since'],
            'headers.expose' => ['Authorization', 'Etag'],
            'credentials' => true,
            'cache' => 60,
            'error' => function (Request $request, Response $response) {
                return $response->withJson(['status' => 'cors  error'], StatusCode::HTTP_UNAUTHORIZED);
            }
        ]));

    }


    /**
     * @param string $userId
     * @param string $duration
     *
     * @return array
     * @throws \Exception
     */
    public static function generateToken(string $userId, string $duration = '+1 year'): array
    {

        $now = new \DateTime('now', ServerUtility::getTimezoneObject());
        $future = new \DateTime($duration, ServerUtility::getTimezoneObject());
        $jti = (new Base62())->encode(random_bytes(16));
        $payload = [
            'iat' => $now->getTimestamp(),
            'exp' => $future->getTimestamp(),
            'jti' => $jti,
            'uid' => $userId
        ];
        $secret = ServerUtility::get('JWT_SECRET');
        $token = JWT::encode($payload, $secret, 'HS256');

        return [
            'token' => $token,
            'expires' => $future->getTimestamp()
        ];
    }


    /**
     * @param User $user
     * @return string
     * @throws \Exception
     */
    public static function generateUserIdentificationToken(User $user): string
    {
        $salt = bin2hex(random_bytes(4));

        return self::getSaltedTokenHash($user->getId(), $user->getCreated()->getTimestamp(), $salt);
    }


    /**
     * @param User $user
     * @param string $claim
     * @return bool
     */
    public static function verifyUserIdentificationToken(User $user, string $claim): bool
    {
        $salt = explode(':', $claim)[0];

        return $claim === self::getSaltedTokenHash($user->getId(), $user->getCreated()->getTimestamp(), $salt);
    }


    /**
     * @param int $id
     * @param int $timestamp
     * @param string $salt
     *
     * @return string
     */
    protected static function getSaltedTokenHash(int $id, int $timestamp, string $salt): string
    {
        $token = sha1($id . $timestamp . $salt . ServerUtility::get('JWT_SECRET'));

        return $salt . ':' . $token;
    }
}