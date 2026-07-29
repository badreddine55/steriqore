/** Mirrors App\Domain\Reporting\Data\DataExportRequestData exactly. */
export type DataExportRequest = {
    id: string;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'expired';
    requested_by_name: string;
    requested_at: string;
    error: string | null;
    completed_at: string | null;
    size_bytes: number | null;
    record_count: number | null;
    file_count: number | null;
    table_manifest: Record<string, number> | null;
    expires_at: string | null;
};
