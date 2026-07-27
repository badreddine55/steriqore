import type { APIRequestContext } from '@playwright/test';

/**
 * The demo practice (database/seeders/DatabaseSeeder.php) — a real owner
 * of a real practice, not a bare login. E2E specs build their own
 * fixture data on top of it via the mobile API (there's no browser-based
 * "create a device"/"start a cycle" screen yet), then drive the actual
 * screen under test through the browser.
 */
export const DEMO_TENANT_SLUG = 'demo';
export const DEMO_EMAIL = 'admin@steriqore.local';
export const DEMO_PASSWORD = 'password';

export async function mintDemoApiToken(
    request: APIRequestContext,
): Promise<string> {
    const response = await request.post('/api/v1/auth/login', {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
        data: {
            tenant_slug: DEMO_TENANT_SLUG,
            email: DEMO_EMAIL,
            password: DEMO_PASSWORD,
        },
    });

    if (!response.ok()) {
        throw new Error(
            `Demo login failed: ${response.status()} ${await response.text()}`,
        );
    }

    const body = (await response.json()) as { token: string };

    return body.token;
}

export async function createDemoDevice(
    request: APIRequestContext,
    token: string,
): Promise<string> {
    const sites = await request.get('/api/v1/sites', {
        headers: { Authorization: `Bearer ${token}` },
    });
    const siteId = (await sites.json()).data[0].id as string;

    const response = await request.post('/api/v1/devices', {
        headers: {
            Authorization: `Bearer ${token}`,
            'Idempotency-Key': crypto.randomUUID(),
        },
        data: {
            site_id: siteId,
            name: `E2E Autoclave ${Date.now()}`,
            serial_number: `E2E-${Date.now()}`,
            kind: 'autoclave',
        },
    });

    if (!response.ok()) {
        throw new Error(
            `Device creation failed: ${response.status()} ${await response.text()}`,
        );
    }

    return (await response.json()).id as string;
}

export async function createDemoCycle(
    request: APIRequestContext,
    token: string,
    deviceId: string,
): Promise<string> {
    const response = await request.post('/api/v1/cycles', {
        headers: {
            Authorization: `Bearer ${token}`,
            'Idempotency-Key': crypto.randomUUID(),
        },
        data: { device_id: deviceId },
    });

    if (!response.ok()) {
        throw new Error(
            `Cycle creation failed: ${response.status()} ${await response.text()}`,
        );
    }

    return (await response.json()).id as string;
}

export async function addDemoCycleItem(
    request: APIRequestContext,
    token: string,
    cycleId: string,
    description: string,
): Promise<void> {
    const response = await request.post(`/api/v1/cycles/${cycleId}/items`, {
        headers: {
            Authorization: `Bearer ${token}`,
            'Idempotency-Key': crypto.randomUUID(),
        },
        data: { description },
    });

    if (!response.ok()) {
        throw new Error(
            `Cycle item creation failed: ${response.status()} ${await response.text()}`,
        );
    }
}

/** Advances draft -> running -> completed, the only state a control test can
 * be recorded from (CYCLE_NOT_READY_FOR_TESTS otherwise). */
export async function completeDemoCycle(
    request: APIRequestContext,
    token: string,
    cycleId: string,
): Promise<void> {
    for (const action of ['start', 'complete']) {
        const response = await request.post(
            `/api/v1/cycles/${cycleId}/${action}`,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Idempotency-Key': crypto.randomUUID(),
                },
                data: {},
            },
        );

        if (!response.ok()) {
            throw new Error(
                `Cycle ${action} failed: ${response.status()} ${await response.text()}`,
            );
        }
    }
}

/** Advances a completed cycle to awaiting_release. */
export async function submitDemoCycleForRelease(
    request: APIRequestContext,
    token: string,
    cycleId: string,
): Promise<void> {
    const response = await request.post(
        `/api/v1/cycles/${cycleId}/submit-for-release`,
        {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {},
        },
    );

    if (!response.ok()) {
        throw new Error(
            `Cycle submit-for-release failed: ${response.status()} ${await response.text()}`,
        );
    }
}

export async function recordDemoControlTest(
    request: APIRequestContext,
    token: string,
    cycleId: string,
): Promise<void> {
    const response = await request.post(
        `/api/v1/cycles/${cycleId}/control-tests`,
        {
            headers: {
                Authorization: `Bearer ${token}`,
                'Idempotency-Key': crypto.randomUUID(),
            },
            data: {
                type: 'vacuum',
                result: 'pass',
                performed_at: new Date().toISOString(),
            },
        },
    );

    if (!response.ok()) {
        throw new Error(
            `Control test creation failed: ${response.status()} ${await response.text()}`,
        );
    }
}
