# API de Webinars

API de solo lectura para consumir submissions, asistentes y estadísticas desde
otras plataformas (ej. `templet_operator`).

- **Base URL:** `https://<host>/api/v1`
- **Documentación Swagger UI:** `https://<host>/api/documentation`
- **Spec OpenAPI (JSON):** `https://<host>/docs/api-docs.json`

## Autenticación

Todas las rutas requieren una API key en el header:

```
X-Api-Key: wbn_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

También se acepta `Authorization: Bearer wbn_...`.

### Generar una llave

```bash
# Llave con acceso a todos los clientes
php artisan api-key:create "templet_operator"

# Llave restringida a un solo cliente
php artisan api-key:create "operator-libertynet" --client=libertynet
```

La llave en texto plano solo se muestra al crearla (en la BD se guarda el hash
SHA-256). Una llave restringida ignora/rechaza el parámetro `client` de otro
cliente con un `403`.

Para revocar: poner `revoked_at` en el registro de `api_keys`.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/v1/submissions` | Registros de las landings, paginados |
| GET | `/api/v1/submissions/{id}` | Detalle de un registro |
| GET | `/api/v1/attendees` | Asistentes reales sincronizados de Zoom |
| GET | `/api/v1/stats` | Métricas por webinar + totales |
| GET | `/api/v1/stats/utm` | Registros agrupados por campo UTM |
| GET | `/api/v1/stats/timeseries` | Registros por día / semana / mes |
| GET | `/api/v1/stats/domains` | Composición de la asistencia por dominio de correo |

Filtros comunes: `client` (slug o ID), `webinar_id`, `webinar_slug`,
`campaign`, `from`, `to`, `per_page`, `page`.

### Campaign Salesforce Field

El campo `campaign` del webinar ("Campaign Salesforce Field" en el admin) es el
identificador con el que se enlaza la campaña en Salesforce. Se puede usar como
filtro en todos los endpoints y viene en cada respuesta como
`webinar.campaign_salesforce_field`.

```bash
curl -H "X-Api-Key: $KEY" \
  "https://<host>/api/v1/submissions?campaign=7013h000000abcAAA"
```

## Audiencia externa vs. interna

Los organizadores, ponentes y la gente del propio cliente se conectan al webinar
y quedan en `attendees`. Contarlos como audiencia infla el número: en el webinar
9, 36 de 225 asistentes únicos eran internos.

`/stats` devuelve las tres cifras por separado en lugar de filtrar a escondidas:
`unique_attendees`, `unique_attendees_external` y `unique_attendees_internal`
(igual para registros). `show_up_rate` y `cost_per_lead` se calculan **sobre
externos**, y cada fila incluye `internal_domains` con los patrones aplicados.

Qué se considera interno:

- Nuestros dominios, en `config/webinars.php` → `internal_domains`.
- Los del cliente, en su ficha del admin (campo "Dominios de correo internos",
  guardado en `clients.internal_email_domains`).

Un patrocinador o ponente externo (ej. `amazon.com`) **no** va en esas listas:
aparece en `/stats/domains` con `internal: false` y lo decide quien lee el
reporte. Filtrar a ciegas fue justo el origen del bug que tenía el dashboard,
donde la lista escrita a mano decía `liberynet` sin la b y nunca excluyó nada.

## Asistentes (Zoom)

Los asistentes se guardan en la tabla `attendees`. La sincronización corre cada
hora (`SyncAttendeesJob`) y también se puede ejecutar a mano:

```bash
php artisan webinars:sync-attendees
php artisan webinars:sync-attendees --webinar=12
```

Solo se sincronizan los webinars con `zoom_webinar_id`, y Zoom publica el
reporte de participantes cuando el webinar ya terminó.

El reporte de Zoom devuelve `name`, `user_email`, `join_time`, `leave_time`,
`duration` y `status`. **No** devuelve ubicación ni dispositivo: las columnas
`location`, `ip_address` y `device` de `attendees` quedan vacías (806 de 806
filas al momento de escribir esto), así que no se exponen en la API. Las
columnas se conservan por si un plan o scope distinto las llena.

## Regenerar la documentación

Después de cambiar anotaciones en `app/Http/Controllers/Api` o
`app/OpenApi/Schemas.php`:

```bash
php artisan l5-swagger:generate
```
