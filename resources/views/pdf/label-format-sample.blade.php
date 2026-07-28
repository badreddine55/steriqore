<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Label format sample</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 12mm;
            color: #111;
        }
        .banner {
            background: #fef3c7;
            border: 1px solid #d97706;
            border-radius: 4px;
            padding: 3mm 5mm;
            margin-bottom: 6mm;
            font-weight: bold;
        }
        .sheet {
            display: grid;
            grid-template-columns: repeat({{ $columns }}, 1fr);
            gap: 4mm;
        }
        .label {
            border: 1px solid #333;
            border-radius: 4px;
            padding: 4mm;
            display: flex;
            gap: 3mm;
        }
        .codes { display: flex; flex-direction: column; align-items: center; gap: 2mm; }
        .codes svg.primary { width: 20mm; height: 20mm; }
        .codes svg.secondary { width: 10mm; height: 10mm; }
        .fields { flex: 1; }
        .fields dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 1mm 2mm; font-size: 8px; }
        .fields dt { font-weight: bold; color: #444; }
        .fields dd { margin: 0; }
        h1 { font-size: 9px; margin: 0 0 2mm 0; }
    </style>
</head>
<body>
    <div class="banner">
        SAMPLE SHEET — not real evidence. Layout: {{ $sheetLayout->label() }}.
    </div>

    <div class="sheet">
        @for ($i = 0; $i < $tileCount; $i++)
            <div class="label">
                <div class="codes">
                    @if ($primaryCode->value === 'datamatrix')
                        <div class="primary">{!! $dataMatrixSvg !!}</div>
                        <div class="secondary">{!! $qrSvg !!}</div>
                    @else
                        <div class="primary">{!! $qrSvg !!}</div>
                        <div class="secondary">{!! $dataMatrixSvg !!}</div>
                    @endif
                </div>
                <div class="fields">
                    <h1>Sample Practice</h1>
                    <dl>
                        <dt>Sterilizer</dt>
                        <dd>Sample Autoclave (SN-000)</dd>

                        <dt>Cycle</dt>
                        <dd>#1</dd>

                        <dt>Sterilized</dt>
                        <dd>{{ now()->format('Y-m-d H:i') }}</dd>

                        <dt>Use by</dt>
                        <dd>{{ now()->addDays(30)->format('Y-m-d') }}</dd>

                        <dt>Operator</dt>
                        <dd>{{ $operatorName }}</dd>

                        <dt>Sequence</dt>
                        <dd>1</dd>

                        @if ($showBatchNumber)
                            <dt>Batch</dt>
                            <dd>SAMPLE-001</dd>
                        @endif

                        @if ($showProductName)
                            <dt>Product</dt>
                            <dd>Sample instrument kit</dd>
                        @endif
                    </dl>
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
