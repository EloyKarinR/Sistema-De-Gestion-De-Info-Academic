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
        .info { width: 100%; margin-bottom: 16px; }
        .info td { padding: 3px 6px; font-size: 12px; }
        .info .label { color: #666; width: 120px; }
        table.grades { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.grades th, table.grades td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
        table.grades th { background-color: #f2f2f2; text-align: left; }
        table.grades td.score { text-align: center; }
        .footer { margin-top: 30px; font-size: 10px; color: #888; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logoPath)
            <img src="{{ $logoPath }}" alt="Logo">
        @endif
        <h1>{{ $institution->name ?? 'Institución Educativa' }}</h1>
        <h2>Boletín de Notas — {{ $enrollment?->academicYear->year ?? '' }}</h2>
    </div>

    <table class="info">
        <tr>
            <td class="label">Estudiante:</td>
            <td><strong>{{ $student->full_name }}</strong></td>
            <td class="label">Cédula:</td>
            <td>{{ $student->cedula ?? '—' }}</td>
        </tr>
        @if ($enrollment)
            <tr>
                <td class="label">Aula:</td>
                <td>{{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}</td>
                <td class="label">Nivel:</td>
                <td>{{ $enrollment->classroom->grade->educationLevel->name }}</td>
            </tr>
            <tr>
                <td class="label">Maestro(a) de Grado:</td>
                <td colspan="3">{{ $homeroomTeacher ?? '—' }}</td>
            </tr>
        @endif
    </table>

    @if (! $enrollment)
        <p>El estudiante no tiene una matrícula activa en el año escolar actual.</p>
    @elseif (empty($matrix))
        <p>Aún no hay notas registradas para este año escolar.</p>
    @else
        <table class="grades">
            <thead>
                <tr>
                    <th>Materia</th>
                    @foreach ($enrollment->academicYear->periods as $period)
                        <th>{{ $period->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix as $subjectName => $periodScores)
                    <tr>
                        <td>{{ $subjectName }}</td>
                        @foreach ($enrollment->academicYear->periods as $period)
                            <td class="score">{{ $periodScores[$period->id] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($enrollment)
        <table class="grades" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Asistencia</th>
                    @foreach ($enrollment->academicYear->periods as $period)
                        <th>{{ $period->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ausencias</td>
                    @foreach ($enrollment->academicYear->periods as $period)
                        <td class="score">{{ $attendance[$period->id]['ausencias'] ?? 0 }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Tardanzas</td>
                    @foreach ($enrollment->academicYear->periods as $period)
                        <td class="score">{{ $attendance[$period->id]['tardanzas'] ?? 0 }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif

    @if ($enrollment && $habits->isNotEmpty())
        <table class="grades" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Hábitos y Actitudes</th>
                    @foreach ($enrollment->academicYear->periods as $period)
                        <th>{{ $period->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($habits as $habit)
                    <tr>
                        <td>{{ $habit->name }}</td>
                        @foreach ($enrollment->academicYear->periods as $period)
                            <td class="score">{{ $habitMatrix[$habit->id][$period->id] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="font-size: 10px; color: #888; margin-top: 4px;">
            S: Satisfactorio &nbsp; R: Regular &nbsp; X: No satisface
        </p>
    @endif

    <div class="footer">
        Emitido el {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
