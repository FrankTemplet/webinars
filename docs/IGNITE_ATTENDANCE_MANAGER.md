# IGNITE — Program Attendance Manager

Notas para entender la herramienta que registra asistencia en Dynamics 365, como base para replicar algo parecido en operator. Todo lo de aquí sale de leer la página en producción y su bundle público el 2026-10-27; las llamadas a Dataverse corren en el servidor y no se pudieron ver.

## Dónde vive

| Pieza | Dirección |
|---|---|
| Entrada en Dynamics | `https://orged7258da.crm.dynamics.com/main.aspx?appid=ea5c5af3-…&pagetype=webresource&webresourceName=cr10e_attendance_manager` (app "Program Manager") |
| Web resource `cr10e_attendance_manager` | Un HTML de ~300 bytes que solo mete un `<iframe>` |
| App real | `https://testing--ignite-attendance-manager.netlify.app/` |

La app es un Next.js (App Router) generado con v0.app, desplegado en Netlify, en la rama `testing`. Usa shadcn/ui, sonner para los avisos y zod para validar. El iframe no le pasa nada a la app: ni token ni parámetros. Toda la lectura y escritura en Dataverse ocurre en **Server Actions** de Next, en el servidor de Netlify, así que ahí tiene que haber credenciales propias de Dataverse (lo más probable, un app registration de Entra ID), no las del usuario que la abre.

La primera carga del 2026-10-27 se quedó en "Loading programs…"; al recargar sí cargó los programas.

## Qué hay en el entorno (2026-10-27)

El entorno `orged7258da` es la app "Program Manager": programas de habilitación de partners de fabricantes.

| Tabla | Registros | Contenido |
|---|---|---|
| Programs (`crdbc_program`) | 8 | CVA Acceleration, OLA Acceleration, Cisco Meraki Lead Generation AU, SOAR #2 Azure / Copilot, más New Program, Test Program y Test Program 2. Tienen meta de leads, pipeline y closed. |
| Program Activities (`cr10e_programactivities`) | 3 | Solo de prueba: Test Activity (Test Program), Test Activity 2 y 3 (Test Program 2). Los programas reales todavía no tienen actividades, así que con ellos el select de Activity sale vacío. |
| Participant Attendance | 11 | Todas en las actividades de prueba. |
| Participants (`contact`) | 25 | Hay duplicados: la misma persona aparece con dos registros. |
| Partners (`account`) | 145 | Las empresas de los partners. |

- **Program:** la iniciativa con un fabricante; por ejemplo, el programa de aceleración de Adobe o la generación de leads de Cisco Meraki.
- **Activity:** cada sesión o hito dentro del programa; por ejemplo, "Milestone 1: Why Adobe…".
- **Attendance:** cuánto participó cada contacto de un partner en cada actividad.

En el menú también aparecen Partner Enrollments, Partner Satisfaction y Participant Interactions, así que la asistencia es una pieza más del seguimiento por partner.

## Server Actions

| Acción | Qué hace (por cómo se usa en el cliente) |
|---|---|
| `getPrograms` | Lista los programas (`crdbc_program`) |
| `getActivities` | Lista las actividades de programa (`cr10e_programactivities`) |
| `getParticipants` | Lista los participantes: contactos (`contact`) con su partner |
| `getPartners` | Lista los partners: cuentas (`account`) |
| `getParticipations` | Lista las asistencias ya registradas (`cr10e_participantattendance`) |
| `createPartner` | Crea una cuenta nueva |
| `createParticipant` | Crea un contacto nuevo ligado a su partner |
| `createParticipations` | Crea asistencias en lote |
| `updateParticipationDuration` | Actualiza la duración de una asistencia que ya existe |

## Modelo de datos en Dataverse

Los prefijos `crdbc_` y `cr10e_` son de dos soluciones distintas.

```
crdbc_program            Programa
  crdbc_programid, crdbc_name, crdbc_programstatus

cr10e_programactivities  Actividad de un programa (una sesión, un milestone)
  cr10e_programactivitiesid, cr10e_name, _cr10e_program_value → programa

account                  Partner (la empresa)
  accountid, name, statuscode

contact                  Participante (la persona)
  contactid, firstname, lastname, emailaddress1
  parentcustomerid → account     (la empresa del contacto)
  crdbc_Partner    → account     (al crear se ligan las dos al mismo partner)

cr10e_participantattendance   Asistencia: una por persona, programa y actividad
  cr10e_Participant → contact
  cr10e_Program     → crdbc_program
  cr10e_Activity    → cr10e_programactivities
  cr10e_duration    minutos (entero)
```

Al crear, las relaciones se mandan con `@odata.bind`: `cr10e_Participant@odata.bind`, `cr10e_Program@odata.bind`, `cr10e_Activity@odata.bind`, `parentcustomerid_account@odata.bind` y `crdbc_Partner@odata.bind`.

## Flujo

1. **Al abrir**, carga en paralelo programas, actividades, participantes y partners. El select de actividades se filtra por el programa elegido.
2. **Excel.** Lee la primera hoja: encabezados en la fila 1 y datos desde la fila 2. Busca cinco columnas por nombre exacto (las demás las ignora):
   - `First Name`
   - `Last Name`
   - `Email Address`
   - `Duration (Minutes)`
   - `Company Name`

   Si falta una, aborta y dice cuál.
3. **Limpieza del correo:** minúsculas, sin espacios ni comillas, y quita el sufijo `+algo` (`ana+test@x.com` → `ana@x.com`).
4. **Validación.** Para el archivo completo si encuentra algún error:
   - El correo no es válido.
   - La duración no es entera, o es negativa.

   **Correo vacío y duración vacía sí pasan.** Una duración vacía queda en 0.
5. **Matching contra los contactos de Dynamics**, fila por fila:
   - **Exact:** coinciden el correo, el nombre y el apellido, los tres exactos.
   - **Possible:** no es exacto, pero hay un contacto de un partner conocido con al menos un campo (nombre, apellido, correo o empresa) cuya similitud llega a 0.7 o más.
     - La similitud es el promedio de tres algoritmos: n-gramas (n=2), Levenshtein y un tercer comparador de strings.
     - El porcentaje que se muestra es el promedio de los cuatro campos.
   - **New:** nada se parece.
6. **Duplicados en el mismo archivo.** Si dos filas se parecen al 95% o más en los cuatro campos, se unen en una sola y sus minutos se suman.
7. **Antes de mostrar resultados, pide resolver:**
   - **Empresas casi iguales:** nombres con similitud de 0.7 o más (por ejemplo "Acme Inc" y "ACME Inc."). El usuario elige cuál es el partner correcto.
   - **Filas incompletas:** sin nombre, apellido, correo o empresa. El usuario las completa.
8. **Matching Results**, en tres pestañas: Exact / Possible / New.
   - **Possible:** el usuario elige el contacto correcto, que puede ser más de uno, y pasa a Exact.
   - **New:** "crear participante".
     - Busca el partner por nombre de empresa (igual, o a menos de 2 cambios de distancia).
     - Si no existe, crea el partner.
     - Luego crea el contacto y lo pasa a Exact.
   - Si un contacto ya está en Exact, no lo repite: le suma los minutos.
9. **Register** (con confirmación). Para cada Exact busca si ya hay asistencia para la misma persona, programa y actividad:
   - **Si ya hay:** `updateParticipationDuration`. Reemplaza la duración, no la suma.
   - **Si no:** la manda a `createParticipations`, en lote.

   Solo se guarda lo que está en Exact. Lo que se quedó en Possible o New no se registra.

## El Excel de ejemplo (`Adobe1 1.xlsx`)

- **Origen:** una hoja "Session Registrants", 50 filas. Es una exportación de registrantes de una plataforma de eventos (columnas como Confirmation Number, Invitee Status y Session Check-In Date), no un reporte de Zoom.
- **Columnas:** 15 en total. La herramienta solo usa las 5 de arriba.
- **Contenido:**
  - Mezcla dos sesiones: "Milestone 1: Why Adobe & The Acrobat Opportunity" (35 filas) y "Milestone 2: How To Sell Adobe Solutions" (15).
  - Son 36 correos únicos de 35 empresas.

Puntos a tener en cuenta con este archivo:

- **`Duration (Minutes)` está vacía en las 50 filas.** La validación la deja pasar, así que todas las asistencias quedarían con 0 minutos. La única señal real de asistencia está en `Session Check-In Date` (29 filas) y `Attendance Type` (7 "Live", 1 "On-Demand"), y la herramienta no lee ninguna de las dos.
- **Las dos sesiones caen en una sola actividad.** La herramienta registra todo contra una actividad, y quien aparece en ambas sesiones se une en un solo registro.
- **Status y check-in no se filtran.** Todas las filas dicen `Accepted`; no se distingue a quien se registró de quien asistió.

## Qué implicaría en operator

Lo que hace es: cargar un archivo, empatar personas con contactos de Dynamics y escribir `cr10e_participantattendance`. Para operator:

- **Fuente de asistencia.** En lugar del Excel, puede salir de la plataforma de webinars: `GET /api/v1/attendees` ya da nombre, correo, `duration_minutes` y webinar. Falta la empresa: hoy está en la submission (registro), no en el attendee.
  - Si la fuente sigue siendo un Excel de otra plataforma como este, hay que decidir qué cuenta como asistencia cuando no viene la duración.
- **Escritura en Dataverse.**
  - Operator ya lee Power BI, pero escribir en Dataverse requiere un app registration con permisos sobre esas tablas.
  - Ojo con los dos prefijos: `crdbc_` (programas, partner del contacto) y `cr10e_` (actividades, asistencia).
- **Qué conviene conservar:**
  - El matching en tres niveles.
  - La revisión manual de Possible.
  - La regla de no duplicar por persona, programa y actividad.
- **Qué conviene corregir:**
  - Que una duración vacía pase como 0.
  - Que el reintento reemplace en vez de sumar sin avisar.
  - Mezclar varias sesiones en una sola actividad.

## Preguntas para quien usa Program Manager

### Para qué se usa
1. ¿Qué reporte o decisión sale de los registros de asistencia? ¿Quién lo ve: el fabricante (Adobe, Cisco, Microsoft), Templet o el partner?
2. ¿Ese reporte se arma dentro de Dynamics, en Power BI o en otro lado?
3. ¿Qué se mide por partner: hitos completados, horas, *Score* y *Completion*? ¿Cómo se calculan esos dos campos?
4. ¿La asistencia tiene consecuencias? Por ejemplo, ¿un partner que no completa los milestones pierde algún beneficio del programa?

### Programas y actividades
5. ¿Quién crea los programas y las actividades, y cuándo? Hoy los programas reales no tienen actividades: ¿todavía no se usa en producción o se registran de otra forma?
6. ¿Una actividad es siempre una sesión en vivo, o también cuenta lo on-demand?
7. Si un mismo evento tiene varias sesiones (Milestone 1 y 2 en el mismo archivo), ¿cada una debe ser una actividad distinta?
8. ¿Alguna de estas actividades es un webinar de nuestra plataforma (webinars.templet.io / Zoom), o todas vienen de plataformas de cada fabricante?

### El archivo que se sube
9. ¿De qué plataforma sale el Excel (el de Adobe parece de una plataforma de eventos, no de Zoom)? ¿Siempre llega con las mismas columnas?
10. El de Adobe no trae `Duration (Minutes)`: ¿qué cuenta como "asistió" cuando no hay duración? ¿El check-in, el *Attendance Type*?
11. ¿Se suben también los registrados que no asistieron, o hay que filtrarlos antes?
12. ¿Cada cuánto se sube y cuánto tarda en llegar el archivo después del evento?

### Personas y empresas
13. ¿Los participantes tienen que existir antes en Dynamics, o está bien que la herramienta los cree al subir?
14. Ya hay contactos duplicados (la misma persona con dos registros): ¿quién los limpia y cuál es el bueno?
15. ¿El partner de una persona sale del nombre de empresa del Excel, o hay una lista oficial de partners por programa (Partner Enrollments)? ¿Se debe rechazar a quien no es de un partner inscrito?
16. ¿Cuenta el correo personal (gmail) o solo el corporativo del partner?

### Reglas al registrar
17. Si alguien entra y sale varias veces, ¿se suman los minutos?
18. Si se vuelve a subir el mismo archivo o uno corregido, ¿se reemplaza la duración o se suma? (Hoy se reemplaza.)
19. ¿Hay un mínimo de minutos para que cuente la asistencia?
20. ¿Hay que registrar al personal interno (Templet, el fabricante) o se excluye?

### Si lo llevamos a operator
21. ¿Te serviría que la asistencia de nuestros webinars llegue sola a Dynamics, sin subir Excel?
22. ¿Quién debe poder registrar asistencia y quién solo verla?
23. ¿Quién administra el entorno de Dynamics y puede darnos un app registration con permiso de escritura en estas tablas?
24. La app actual está en Netlify en una rama `testing`: ¿quién la hizo, la mantiene alguien, y hay otro entorno de producción?
