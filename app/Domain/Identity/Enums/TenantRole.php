<?php

namespace App\Domain\Identity\Enums;

/**
 * The six roles from CLAUDE.md's RBAC section. Not yet wired to
 * spatie/laravel-permission (that's S1-ANA-03) — used for now just to
 * validate the `role` field on an invitation against a fixed, known set.
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case StockManager = 'stock_manager';
    case Releaser = 'releaser';
    case Practitioner = 'practitioner';
    case Viewer = 'viewer';
    case Reception = 'reception';
}
