/** Mirrors App\Domain\Traceability\Data\PatientData exactly. */
export type Patient = {
    id: string;
    reference: string;
};

export type PatientFilters = {
    reference?: string;
};

/** Mirrors App\Domain\Traceability\Data\PatientUsageEntryData exactly. */
export type PatientUsageEntry = {
    id: string;
    label_id: string;
    practitioner_name: string;
    procedure: string;
    used_at: string;
    notes: string | null;
};

/** Mirrors App\Domain\Traceability\Data\EvidenceSearchResultData exactly. */
export type EvidenceSearchResult = {
    label_id: string;
    label_status: string;
    cycle_number: number;
    device_name: string;
    site_name: string;
    operator_name: string;
    batch_number: string | null;
    patient_reference: string;
    practitioner_name: string;
    procedure: string;
    used_at: string;
};

export type EvidenceSearchFilters = {
    patient_reference?: string;
    cycle_number?: number;
    batch_number?: string;
    device_id?: string;
    operator_id?: string;
    from?: string;
    to?: string;
};
