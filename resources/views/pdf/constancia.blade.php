<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header img { height: 50px; margin-bottom: 6px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .header h2 { font-size: 14px; margin: 0; font-weight: normal; color: #555; }
        .receipt-number { text-align: right; font-size: 11px; color: #666; margin-bottom: 10px; }
        .section { margin-bottom: 16px; }
        .section h3 { font-size: 13px; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 8px; }
        table.data { width: 100%; }
        table.data td { padding: 3px 6px; font-size: 12px; vertical-align: top; }
        table.data .label { color: #666; width: 140px; }
        .docs span { display: inline-block; margin-right: 10px; }
        .footer { margin-top: 40px; font-size: 10px; color: #888; text-align: right; }
        .signature { margin-top: 60px; text-align: center; }
        .signature .line { border-top: 1px solid #333; width: 250px; margin: 0 auto; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logoPath)
            <img src="{{ $logoPath }}" alt="Logo">
        @endif
        <h1>{{ $institution->name ?? 'Institución Educativa' }}</h1>
        <h2>Constancia de Matrícula</h2>
    </div>

    <div class="receipt-number">N.° {{ $enrollment->receipt_number ?? $enrollment->id }}</div>

    <div class="section">
        <h3>Estudiante</h3>
        <table class="data">
            <tr>
                <td class="label">Nombre:</td>
                <td><strong>{{ $enrollment->student->full_name }}</strong></td>
                <td class="label">Cédula:</td>
                <td>{{ $enrollment->student->cedula ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Matrícula</h3>
        <table class="data">
            <tr>
                <td class="label">Año escolar:</td>
                <td>{{ $enrollment->academicYear->year }}</td>
                <td class="label">Fecha:</td>
                <td>{{ $enrollment->enrollment_date->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Aula:</td>
                <td>{{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}</td>
                <td class="label">Nivel:</td>
                <td>{{ $enrollment->classroom->grade->educationLevel->name }}</td>
            </tr>
            <tr>
                <td class="label">Turno:</td>
                <td>{{ \App\Enums\Shift::from($enrollment->classroom->shift)->labelWithTime() }}</td>
                <td class="label">Tipo:</td>
                <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $enrollment->enrollment_type) }}</td>
            </tr>
        </table>
    </div>

    @if ($enrollment->student->guardians->isNotEmpty())
        @php $guardian = $enrollment->student->guardians->first(); @endphp
        <div class="section">
            <h3>Acudiente</h3>
            <table class="data">
                <tr>
                    <td class="label">Nombre:</td>
                    <td>{{ $guardian->full_name }}</td>
                    <td class="label">Parentesco:</td>
                    <td style="text-transform: capitalize;">{{ $guardian->relationship }}</td>
                </tr>
                <tr>
                    <td class="label">Teléfono:</td>
                    <td>{{ $guardian->primary_phone }}</td>
                    <td class="label">Cédula:</td>
                    <td>{{ $guardian->cedula ?? '—' }}</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="section docs">
        <h3>Documentos entregados</h3>
        <span>{{ $enrollment->doc_cedula_student ? '[X]' : '[ ]' }} Cédula del estudiante</span>
        <span>{{ $enrollment->doc_cedula_guardian ? '[X]' : '[ ]' }} Cédula del acudiente</span>
        <span>{{ $enrollment->doc_boletin ? '[X]' : '[ ]' }} Boletín</span>
        <span>{{ $enrollment->doc_foto ? '[X]' : '[ ]' }} Foto</span>
        <span>{{ $enrollment->doc_address ? '[X]' : '[ ]' }} Dirección</span>
    </div>

    <div class="signature">
        <div class="line">Firma del acudiente</div>
    </div>

    <div class="footer">
        Registrado por {{ $enrollment->registeredBy?->name ?? '—' }} · Emitido el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
