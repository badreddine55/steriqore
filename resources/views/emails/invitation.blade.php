@component('mail::message')
# You're invited

You've been invited to join **{{ $tenantName }}** on SteriQore as **{{ $role }}**.

@component('mail::button', ['url' => $acceptUrl])
Accept invitation
@endcomponent

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
SteriQore
@endcomponent
