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
