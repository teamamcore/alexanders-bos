<?php

declare(strict_types=1);

namespace App\Platform\Migration;

use RuntimeException;

/**
 * SOW §4 — the direct QuickBooks Online connection (OAuth 2.0). Reports
 * whether the four credentials are present; the live pull (Customer, Item,
 * Invoice queries via the QBO v3 API) is wired once the Client grants
 * administrative access. Until then imports run from exported files.
 */
final class QuickBooksOnline
{
    public static function configured(): bool
    {
        $c = config('quickbooks.online', []);

        return (string) ($c['client_id'] ?? '') !== '' && (string) ($c['client_secret'] ?? '') !== ''
            && (string) ($c['realm_id'] ?? '') !== '' && (string) ($c['refresh_token'] ?? '') !== '';
    }

    /** @return array{configured: bool, realm: ?string, environment: string, missing: list<string>} */
    public static function status(): array
    {
        $c = config('quickbooks.online', []);
        $missing = [];
        foreach (['client_id' => 'QBO_CLIENT_ID', 'client_secret' => 'QBO_CLIENT_SECRET', 'realm_id' => 'QBO_REALM_ID', 'refresh_token' => 'QBO_REFRESH_TOKEN'] as $k => $env) {
            if ((string) ($c[$k] ?? '') === '') {
                $missing[] = $env;
            }
        }

        return ['configured' => $missing === [], 'realm' => $c['realm_id'] ?? null, 'environment' => (string) ($c['environment'] ?? 'production'), 'missing' => $missing];
    }

    /**
     * Pull one kind straight from QuickBooks Online and write it to a CSV the
     * importer understands. Guarded until credentials exist.
     */
    public static function exportToCsv(): string
    {
        if (! self::configured()) {
            throw new RuntimeException('QuickBooks Online is not connected — set QBO_CLIENT_ID, QBO_CLIENT_SECRET, QBO_REALM_ID and QBO_REFRESH_TOKEN, or import an exported file.');
        }
        throw new RuntimeException('QuickBooks Online live pull is pending Client access (SOW §9) — import an exported file for now.');
    }
}
