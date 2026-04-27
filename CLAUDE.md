# SIGA — Sistema de Gestión de Información Académica

## Sobre el proyecto
Proyecto universitario con visión comercial: venderlo a colegios/escuelas de primaria y secundaria después de la U.

## Stack
- Laravel 13 + PHP 8.5.5
- Livewire 4 + Flux 2
- PostgreSQL
- Fortify (auth)
- Teams (multi-tenant ya instalado)
- laravel/boost v2.4.3 (dev) — instalado y configurado para Claude Code
- laravel/mcp v0.6.5
- laravel/roster v0.5.1

## Estrategia
MVP bien definido para la U, arquitectura escalable para comercializar después. Priorizar calidad y completitud sobre cantidad de módulos. Código limpio y bien normalizado desde el inicio.

## MVP — Módulos prioritarios
1. Institución (datos del colegio)
2. Académico (grados, secciones, turnos, periodos)
3. Personas (alumnos, apoderados, docentes)
4. Matrículas (módulo estrella)
5. Reportes básicos (PDF)
6. Roles y permisos (Admin, Secretaria, Docente)

## Para escalar después
Multi-tenant, notas, asistencia, pagos reales, portal padres.

## Dominio — Proceso de matrícula actual
- Presencial y tedioso: padres hacen filas desde temprano para matricular a sus hijos
- Documentos requeridos: copia de cédula del acudiente, copia de cédula del alumno, teléfono, copia del boletín, dirección
- Al finalizar entregan un recibo en papel con todos los datos y el grado asignado
- Un estudiante solo puede estar en UN aula de clase
- Los periodos académicos son por trimestre (3 por año)

## Campos sugeridos para la BD
**Estudiante:** fecha de nacimiento, sexo, lugar de nacimiento, tipo de sangre, condiciones médicas/alergias, colegio de procedencia, foto

**Acudiente:** nombre completo, parentesco, teléfono principal, teléfono de emergencia, correo electrónico, ocupación

**Matrícula:** fecha de matrícula, estado (activo/retirado/trasladado), año escolar, usuario que registró

## Próximo paso
- Diseñar el esquema de la base de datos antes de escribir cualquier migración
- Usuario va a traer: recibo de matrícula real, boletín de notas, y lista de entidades del sistema

## Sobre el usuario
- Estudiante universitario, trabaja solo en el proyecto
- Conoce Laravel (starter kit ya configurado con Livewire + Flux)
- Prefiere respuestas directas y decisiones proactivas
