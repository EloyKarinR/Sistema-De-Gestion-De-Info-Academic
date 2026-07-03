---
name: Project Context
description: SIGA — Sistema de Gestión de Información Académica para colegios, proyecto universitario con visión comercial
type: project
originSessionId: d663d228-78bb-415e-8d51-fd83501bb2ae
---
**Proyecto:** SIGA (Sistema de Gestión de Información Académica)

**Why:** Es proyecto universitario pero con visión de venderlo a colegios/escuelas después.

**Stack:** Laravel 13 + PHP 8.5.5 + Livewire 4 + Flux 2 + PostgreSQL + Fortify auth + Teams (multi-tenant listo) + laravel/boost v2.4.3 (dev, pendiente `php artisan boost:install`)

**Estrategia:** MVP bien definido para la U, arquitectura escalable para comercializar después.

**MVP para la U (módulos prioritarios):**
1. Institución (datos del colegio)
2. Académico (grados, secciones, turnos, periodos)
3. Personas (alumnos, apoderados, docentes)
4. Matrículas (módulo estrella)
5. Reportes básicos (PDF)
6. Roles y permisos (Admin, Secretaria, Docente)

**Para escalar después:** multi-tenant (Teams ya instalado), notas, asistencia, pagos reales, portal padres.

**How to apply:** Priorizar calidad y completitud del MVP sobre cantidad de módulos. Código limpio y bien normalizado desde el inicio.

**Estado real al 2026-07-02 (esta memoria estaba desactualizada — la BD y varios módulos YA se implementaron):**
- BD diseñada e implementada: 27 migraciones (institutions, academic_years, periods, education_levels, grades, classrooms, subjects, students, guardians, teachers, enrollments, payments, grade_scores, attendance, rehabilitations, etc.) + `diagrama.mmd` con el ER completo.
- Seeders: roles, institución, año escolar 2026, grados, materias, hábitos, usuarios, estudiantes.
- Módulos Livewire construidos (`resources/views/pages/`): Institución (con logo), Académico (año escolar + aulas), Estudiantes (listado + búsqueda + ficha), Matrículas (form multi-paso + recibo imprimible).
- Multi-tenant con Teams funcionando (rutas con `{current_team}`).
- Todo esto quedó en un solo commit "feat: initial commit — SIGA MVP foundation" (2026-04-26), co-autoría de una sesión anterior con Claude.
- Falta verificar: si las migraciones ya corrieron contra una BD real, si hay tests, y si los módulos fueron probados en el navegador.

**Dominio — Proceso de matrícula:**
- Actualmente es presencial y tedioso: padres hacen filas desde temprano
- Documentos que piden: copia de cédula del acudiente, copia de cédula del alumno, teléfono, copia del boletín, dirección
- Al finalizar dan un recibo en papel con todos los datos y el grado asignado
- Un estudiante solo puede estar en UN aula de clase
- Los periodos académicos son por trimestre (3 por año)

**Decisión (2026-07-02): entrevistas a colegios canceladas por ahora.**
- **Why:** poco tiempo antes de la presentación del proyecto para la U. El levantamiento de requisitos con una institución real no es bloqueante para seguir construyendo el MVP — ya hay suficiente información de dominio (recogida directamente del usuario) para avanzar.
- **How to apply:** no proponer ni programar entrevistas/visitas a colegios ni pedir el acta llena mientras se esté construyendo el MVP. `acta_entrevista.md`/`.pdf` sigue siendo una plantilla vacía intencionalmente — es correcto que así sea por ahora.
- **Retomar después de presentar el proyecto en la U**, como parte de la fase de escalar a colegios reales (ver "Para escalar después" arriba). En ese momento sí conviene validar el esquema de BD y los flujos contra una institución real antes de vender.
