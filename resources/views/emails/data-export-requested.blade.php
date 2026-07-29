@component('mail::message')
# {{ $tenantName }} — data export requested

Hi {{ $recipientName }},

@if ($isSelf)
You just requested a full data export for **{{ $tenantName }}**.
@else
**{{ $requestedByName }}** requested a full data export for **{{ $tenantName }}**.
@endif

This export will contain every record and attached file stored for your
practice, including pseudonymized patient references. You're receiving this
notice because you're an owner of this practice — if you didn't expect this,
check the audit log and consider revoking access for whoever requested it.

Thanks,<br>
SteriQore
@endcomponent
