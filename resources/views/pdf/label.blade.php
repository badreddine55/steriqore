<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Label {{ $label->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 12mm;
            color: #111;
        }
        .label {
            border: 1px solid #333;
            border-radius: 4px;
            padding: 8mm;
            display: flex;
            gap: 8mm;
        }
        .codes {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4mm;
        }
        .codes svg { width: 28mm; height: 28mm; }
        .fields { flex: 1; }
        .fields dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 2mm 4mm; }
        .fields dt { font-weight: bold; color: #444; }
        .fields dd { margin: 0; }
        h1 { font-size: 13px; margin: 0 0 4mm 0; }
    </style>
</head>
<body>
    <div class="label">
        <div class="codes">
            {!! $qrSvg !!}
            {!! $dataMatrixSvg !!}
        </div>
        <div class="fields">
            <h1>{{ $site->name }}</h1>
            <dl>
                <dt>Sterilizer</dt>
                <dd>{{ $device->name }} ({{ $device->serial_number }})</dd>

                <dt>Cycle</dt>
                <dd>#{{ $cycle->cycle_number }}</dd>

                <dt>Sterilized</dt>
                <dd>{{ $label->sterilized_at->format('Y-m-d H:i') }}</dd>

                <dt>Use by</dt>
                <dd>{{ $label->use_by_date->format('Y-m-d') }}</dd>

                <dt>Operator</dt>
                <dd>{{ $operator->name }}</dd>

                <dt>Sequence</dt>
                <dd>{{ $item->sequence_in_cycle }}</dd>

                <dt>Contents</dt>
                <dd>{{ $item->description }}</dd>
            </dl>
        </div>
    </div>
</body>
</html>
