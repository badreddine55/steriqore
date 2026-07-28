/** Mirrors App\Domain\Tenancy\Data\PracticeIdentityData exactly. */
export type PracticeIdentity = {
    id: string;
    name: string;
    legal_name: string | null;
    address_line1: string | null;
    address_line2: string | null;
    postal_code: string | null;
    city: string | null;
    country_code: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    timezone: string;
    locale: string;
    logo_url: string | null;
};

/** Mirrors App\Domain\Labeling\Data\LabelFormatVersionData exactly. */
export type LabelFormatVersion = {
    primary_code: 'qr' | 'datamatrix';
    sheet_layout: 'a4_24' | 'a4_65' | 'roll';
    show_batch_number: boolean;
    show_product_name: boolean;
    effective_from: string;
    updated_by: string | null;
    reason: string;
};

/** Mirrors App\Domain\Inventory\Data\AlertSettingsData exactly. */
export type AlertSettings = {
    near_expiry_days: number;
    default_low_stock_threshold: number;
    control_test_frequency_days: number | null;
    control_test_overdue_tolerance_days: number | null;
    failed_cycle_escalation_hours: number;
    digests_enabled: boolean;
};

/** Mirrors App\Domain\Inventory\Data\DigestSubscriptionData exactly. */
export type DigestSubscription = {
    role: string;
    frequency: 'daily' | 'weekly';
    enabled: boolean;
    last_sent_at: string | null;
};
