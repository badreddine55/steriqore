<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Evidence dossier {{ $label->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; margin: 0; padding: 15mm; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2mm 0; }
        h2 { font-size: 12px; margin: 6mm 0 2mm 0; border-bottom: 1px solid #ccc; padding-bottom: 1mm; }
        .subtitle { color: #555; margin-bottom: 6mm; }
        dl { margin: 0; display: grid; grid-template-columns: 40mm 1fr; gap: 1.5mm 4mm; }
        dt { font-weight: bold; color: #444; }
        dd { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        th, td { text-align: left; padding: 1.5mm 2mm; border-bottom: 1px solid #eee; font-size: 10px; }
        th { color: #444; }
        .qr { float: right; width: 24mm; height: 24mm; }
        .pass { color: #0a7a2f; }
        .fail { color: #b3241c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="qr">{!! $qrSvg !!}</div>
    <h1>Evidence dossier</h1>
    <div class="subtitle">{{ $site->name }} &middot; Cycle #{{ $cycle->cycle_number }} &middot; Label {{ $label->id }}</div>

    <h2>Sterilization cycle</h2>
    <dl>
        <dt>Device</dt><dd>{{ $device->name }} ({{ $device->serial_number }})</dd>
        <dt>Program</dt><dd>{{ $cycle->deviceProgram?->name ?? '—' }}</dd>
        <dt>Operator</dt><dd>{{ $cycle->operator->name }}</dd>
        <dt>Started</dt><dd>{{ $cycle->started_at?->format('Y-m-d H:i') ?? '—' }}</dd>
        <dt>Completed</dt><dd>{{ $cycle->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
    </dl>

    <h2>Control tests</h2>
    @if ($controlTests->isEmpty())
        <p>No control tests recorded.</p>
    @else
        <table>
            <thead><tr><th>Type</th><th>Result</th><th>Performed at</th></tr></thead>
            <tbody>
                @foreach ($controlTests as $test)
                    <tr>
                        <td>{{ $test->type->value }}</td>
                        <td class="{{ $test->result->value === 'pass' ? 'pass' : 'fail' }}">{{ $test->result->value }}</td>
                        <td>{{ $test->performed_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Release</h2>
    <dl>
        <dt>Decision</dt><dd>{{ $release->decision->value }}</dd>
        <dt>Released by</dt><dd>{{ $release->releasedBy->name }}</dd>
        <dt>Released at</dt><dd>{{ $release->released_at->format('Y-m-d H:i') }}</dd>
        @if ($release->reason)
            <dt>Reason</dt><dd>{{ $release->reason }}</dd>
        @endif
    </dl>

    <h2>Label</h2>
    <dl>
        <dt>Status</dt><dd>{{ $label->status->value }}</dd>
        <dt>Packaging</dt><dd>{{ $label->packaging_type }} &middot; {{ $label->storage_condition }}</dd>
        <dt>Sterilized</dt><dd>{{ $label->sterilized_at->format('Y-m-d H:i') }}</dd>
        <dt>Use by</dt><dd>{{ $label->use_by_date->format('Y-m-d') }}</dd>
        <dt>Batch</dt><dd>{{ $item->batch?->batch_number ?? '—' }}</dd>
        <dt>Print count</dt><dd>{{ $label->print_counter }}</dd>
    </dl>

    <h2>Usage</h2>
    <dl>
        <dt>Patient</dt><dd>{{ $usage->patient->reference }}</dd>
        <dt>Practitioner</dt><dd>{{ $usage->practitioner->name }}</dd>
        <dt>Procedure</dt><dd>{{ $usage->procedure }}</dd>
        <dt>Used at</dt><dd>{{ $usage->used_at->format('Y-m-d H:i') }}</dd>
    </dl>
</body>
</html>
