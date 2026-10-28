<?php

namespace App\Support;

final class AuthRateLimitKey
{
    public static function forEmail(
        string $scope,
        string $email
    ): string {
        return self::buildKey(
            $scope,
            mb_strtolower(trim($email), 'UTF-8')
        );
    }

    public static function forIp(
        string $scope,
        ?string $ip
    ): string {
        return self::buildKey(
            $scope,
            trim((string) $ip)
        );
    }

    private static function buildKey(
        string $scope,
        string $value
    ): string {
        $applicationKey = (string) config(
            'app.key',
            ''
        );

        $digest = $applicationKey === ''
            ? hash('sha256', $value)
            : hash_hmac(
                'sha256',
                $value,
                $applicationKey
            );

        return $scope.':'.$digest;
    }
}
