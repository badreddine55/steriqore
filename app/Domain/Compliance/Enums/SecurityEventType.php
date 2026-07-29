<?php

namespace App\Domain\Compliance\Enums;

enum SecurityEventType: string
{
    case AuthorizationFailure = 'authorization_failure';
    case CrossTenantResolutionAttempt = 'cross_tenant_resolution_attempt';
    case RlsPolicyRejection = 'rls_policy_rejection';
}
