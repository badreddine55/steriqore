/** Mirrors App\Domain\Identity\Data\TeamMemberData exactly. */
export type TeamMember = {
    id: string;
    user_id: string;
    name: string;
    email: string;
    role: string | null;
    status: string;
    joined_at: string | null;
    disabled_at: string | null;
};

/** Mirrors App\Domain\Identity\Data\InvitationData exactly. */
export type Invitation = {
    id: string;
    email: string;
    role: string;
    expires_at: string;
    created_at: string;
    accepted_at: string | null;
    revoked_at: string | null;
    invited_by_name: string | null;
};
