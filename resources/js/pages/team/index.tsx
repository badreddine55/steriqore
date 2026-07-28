import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import {
    invitationStatus,
    memberStatus,
    tenantRoleLabel,
} from '@/lib/status';
import { dashboard } from '@/routes';
import team from '@/routes/team';
import teamInvitations from '@/routes/team/invitations';
import teamMembers from '@/routes/team/members';
import type { Invitation, TeamMember } from '@/types';

const ROLE_OPTIONS = Object.keys(tenantRoleLabel);

function invitationState(invitation: Invitation): keyof typeof invitationStatus {
    if (invitation.revoked_at) {
        return 'revoked';
    }

    if (invitation.accepted_at) {
        return 'accepted';
    }

    if (new Date(invitation.expires_at) < new Date()) {
        return 'expired';
    }

    return 'pending';
}

function InviteMemberDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm({ email: '', role: '' });

    function submit() {
        form.post(teamInvitations.store().url, {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" />
                    Invite member
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite a team member</DialogTitle>
                    <DialogDescription>
                        Sends an email with a link to set up their account.
                        The invitation expires after 7 days.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="invite-email">Email</Label>
                        <Input
                            id="invite-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        {form.errors.email && (
                            <p className="text-sm text-destructive">
                                {form.errors.email}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="invite-role">Role</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData('role', value)
                            }
                        >
                            <SelectTrigger id="invite-role" className="w-full">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                {ROLE_OPTIONS.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {tenantRoleLabel[role]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.role && (
                            <p className="text-sm text-destructive">
                                {form.errors.role}
                            </p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={
                            form.processing ||
                            !form.data.email.trim() ||
                            !form.data.role
                        }
                    >
                        Send invitation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function DisableMemberDialog({ member }: { member: TeamMember }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button size="sm" variant="destructive">
                    Disable access
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Disable {member.name}&apos;s access?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Revokes every active session immediately — not just
                        at next login. Their identity stays on every past
                        record exactly as it is; accounts are disabled,
                        never deleted.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() =>
                            router.delete(
                                teamMembers.disable(member.id).url,
                                { preserveScroll: true },
                            )
                        }
                    >
                        Disable access
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function RevokeInvitationDialog({ invitation }: { invitation: Invitation }) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Revoke
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Revoke the invitation to {invitation.email}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        The invite link stops working immediately. They can
                        be invited again later if needed.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() =>
                            router.delete(
                                teamInvitations.revoke(invitation.id).url,
                                { preserveScroll: true },
                            )
                        }
                    >
                        Revoke invitation
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export default function TeamIndex({
    members,
    invitations,
    currentUserId,
}: {
    members: TeamMember[];
    invitations: Invitation[];
    currentUserId: string;
}) {
    return (
        <>
            <Head title="Team" />

            <div className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-semibold tracking-tight">
                            Team
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Who has access to this practice, and what
                            they&apos;re invited to.
                        </p>
                    </div>
                    <div className="no-print">
                        <InviteMemberDialog />
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Team members
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-hidden rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-36">
                                            Joined
                                        </TableHead>
                                        <TableHead className="no-print w-32" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {members.map((member) => (
                                        <TableRow key={member.id}>
                                            <TableCell>
                                                {member.name}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {member.email}
                                            </TableCell>
                                            <TableCell>
                                                {member.role
                                                    ? (tenantRoleLabel[
                                                          member.role
                                                      ] ?? member.role)
                                                    : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        memberStatus[
                                                            member.status
                                                        ] ??
                                                        memberStatus.active
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {member.joined_at
                                                    ? formatDateTime(
                                                          member.joined_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="no-print">
                                                {member.status ===
                                                    'active' &&
                                                    member.user_id !==
                                                        currentUserId && (
                                                        <DisableMemberDialog
                                                            member={member}
                                                        />
                                                    )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            Invitations
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {invitations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No invitations have been sent yet.
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Email</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>
                                                Invited by
                                            </TableHead>
                                            <TableHead className="w-36">
                                                Sent
                                            </TableHead>
                                            <TableHead className="no-print w-24" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invitations.map((invitation) => {
                                            const state =
                                                invitationState(invitation);

                                            return (
                                                <TableRow key={invitation.id}>
                                                    <TableCell>
                                                        {invitation.email}
                                                    </TableCell>
                                                    <TableCell>
                                                        {tenantRoleLabel[
                                                            invitation.role
                                                        ] ?? invitation.role}
                                                    </TableCell>
                                                    <TableCell>
                                                        <StatusBadge
                                                            status={
                                                                invitationStatus[
                                                                    state
                                                                ]
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {invitation.invited_by_name ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {formatDateTime(
                                                            invitation.created_at,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="no-print">
                                                        {state ===
                                                            'pending' && (
                                                            <RevokeInvitationDialog
                                                                invitation={
                                                                    invitation
                                                                }
                                                            />
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TeamIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Team', href: team.index() },
    ],
};
