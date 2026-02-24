<?php

namespace App\Support;

class UiFeature
{
    public const REACT_SANDBOX = 'react_sandbox';
    public const REACT_PUBLIC_PRODUCTS = 'react_public_products';
    public const ADMIN_AFFILIATE_COMMISSIONS_INDEX = 'admin_affiliate_commissions_index';
    public const ADMIN_AFFILIATE_PAYOUTS_UI = 'admin_affiliate_payouts_ui';

    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return [
            self::REACT_SANDBOX => self::enabled(self::REACT_SANDBOX),
            self::REACT_PUBLIC_PRODUCTS => self::enabled(self::REACT_PUBLIC_PRODUCTS),
            self::ADMIN_AFFILIATE_COMMISSIONS_INDEX => self::enabled(self::ADMIN_AFFILIATE_COMMISSIONS_INDEX),
            self::ADMIN_AFFILIATE_PAYOUTS_UI => self::enabled(self::ADMIN_AFFILIATE_PAYOUTS_UI),
        ];
    }
}
