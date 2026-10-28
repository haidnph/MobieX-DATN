<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AuthRateLimitKey;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(
            static function (
                User $user,
                string $token
            ): string {
                $frontendUrl = rtrim(
                    (string) config('app.frontend_url'),
                    '/'
                );

                return $frontendUrl . '/reset-password?' .
                    http_build_query([
                        'token' => $token,
                        'email' => $user->getEmailForPasswordReset(),
                    ]);
            }
        );

        // Register: maximum 5 requests per IP per hour.
        RateLimiter::for(
            'auth-register',
            function (Request $request): Limit {
                return Limit::perHour(5)->by(
                    AuthRateLimitKey::forIp(
                        'auth:register:ip',
                        $request->ip()
                    )
                );
            }
        );

        // Login: maximum 30 requests per IP per minute.
        // Failed attempts per normalized email are counted
        // separately inside LoginController.
        RateLimiter::for(
            'auth-login',
            function (Request $request): Limit {
                return Limit::perMinute(30)->by(
                    AuthRateLimitKey::forIp(
                        'auth:login:ip',
                        $request->ip()
                    )
                );
            }
        );

        // Forgot password:
        // 3 requests per email per 15 minutes,
        // plus 10 requests per IP per hour.
        RateLimiter::for(
            'auth-forgot-password',
            function (Request $request): array {
                $limits = [
                    Limit::perHour(10)->by(
                        AuthRateLimitKey::forIp(
                            'auth:forgot:ip',
                            $request->ip()
                        )
                    ),
                ];

                $email = trim(
                    (string) $request->input('email', '')
                );

                if ($email !== '') {
                    $limits[] = Limit::perMinutes(15, 3)->by(
                        AuthRateLimitKey::forEmail(
                            'auth:forgot:email',
                            $email
                        )
                    );
                }

                return $limits;
            }
        );

        // Reset password:
        // 5 requests per email per 15 minutes,
        // plus 10 requests per IP per 15 minutes.
        RateLimiter::for(
            'auth-reset-password',
            function (Request $request): array {
                $limits = [
                    Limit::perMinutes(15, 10)->by(
                        AuthRateLimitKey::forIp(
                            'auth:reset:ip',
                            $request->ip()
                        )
                    ),
                ];

                $email = trim(
                    (string) $request->input('email', '')
                );

                if ($email !== '') {
                    $limits[] = Limit::perMinutes(15, 5)->by(
                        AuthRateLimitKey::forEmail(
                            'auth:reset:email',
                            $email
                        )
                    );
                }

                return $limits;
            }
        );
    }
}
