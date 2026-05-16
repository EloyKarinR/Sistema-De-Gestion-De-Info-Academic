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

**Próximo paso acordado:** Diseñar e implementar la base de datos (migraciones). Pendiente para una sesión futura.

**Dominio — Proceso de matrícula:**
- Actualmente es presencial y tedioso: padres hacen filas desde temprano
- Documentos que piden: copia de cédula del acudiente, copia de cédula del alumno, teléfono, copia del boletín, dirección
- Al finalizar dan un recibo en papel con todos los datos y el grado asignado
- Un estudiante solo puede estar en UN aula de clase
- Los periodos académicos son por trimestre (3 por año)
- Pendiente: usuario va a conseguir recibo de matrícula real y boletín de notas para la próxima sesión
- Pendiente: lista de entidades del sistema (próxima sesión)
