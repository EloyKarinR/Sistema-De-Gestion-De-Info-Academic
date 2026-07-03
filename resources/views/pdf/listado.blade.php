<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .header h2 { font-size: 14px; margin: 0; font-weight: normal; color: #555; }
        table.roster { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.roster th, table.roster td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
        table.roster th { background-color: #f2f2f2; text-align: left; }
        table.roster td.num { width: 30px; text-align: center; color: #888; }
        .footer { margin-top: 30px; font-size: 10px; color: #888; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institution->name ?? 'Institución Educativa' }}</h1>
        <h2>
            Listado de Estudiantes — {{ $classroom->grade->name }}-{{ $classroom->section }}
            ({{ $classroom->grade->educationLevel->name }}, turno {{ \App\Enums\Shift::from($classroom->shift)->labelWithTime() }})
        </h2>
    </div>

    @if ($enrollments->isEmpty())
        <p>No hay estudiantes matriculados activos en esta aula.</p>
    @else
        <table class="roster">
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>Nombre</th>
                    <th>Cédula</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($enrollments as $i => $enrollment)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ $enrollment->student->full_name }}</td>
                        <td>{{ $enrollment->student->cedula ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Total: {{ $enrollments->count() }} estudiante(s) · Emitido el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
