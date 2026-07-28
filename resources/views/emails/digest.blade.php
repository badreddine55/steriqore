@component('mail::message')
# {{ $tenantName }} — open alerts

Hi {{ $recipientName }},

Here's a summary of every currently open alert for **{{ $tenantName }}**.

@foreach ($alertsByType as $type => $alerts)
## {{ ucfirst(str_replace('_', ' ', $type)) }} ({{ $alerts->count() }})

@foreach ($alerts as $alert)
- {{ $alert->message }}
@endforeach

@endforeach

You're receiving this because your role is subscribed to this digest. An
owner or admin can change who receives it, or turn it off entirely, from
Practice Settings.

Thanks,<br>
SteriQore
@endcomponent
