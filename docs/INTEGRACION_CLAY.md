# Integración con Clay

## Descripción

Esta integración permite enviar automáticamente los datos de los registros de webinars a Clay para su enriquecimiento. Clay es una plataforma de enriquecimiento de datos que puede agregar información adicional sobre los contactos como:

- Información de empresa (tamaño, industria, ubicación)
- Perfil de LinkedIn
- Datos de contacto adicionales
- Señales de intención de compra
- Y mucho más

## Configuración en Clay

### 1. Crear un Webhook en Clay

1. Ve a tu workspace de Clay
2. Crea una nueva tabla o abre una existente
3. Haz clic en "Add Data" → "Webhook"
4. Clay te proporcionará una URL de webhook única
5. Copia esta URL (será algo como: `https://clay.com/webhook/abc123...`)

### 2. Configurar el Workflow de Enriquecimiento

En Clay, puedes configurar qué enriquecimientos deseas aplicar automáticamente:

1. Agrega columnas de enriquecimiento (ej: LinkedIn Profile Enrichment, Company Data, etc.)
2. Configura los enriquecimientos para que se ejecuten automáticamente cuando llegue un nuevo registro
3. Opcionalmente, configura acciones posteriores como:
   - Enviar a un CRM (HubSpot, Salesforce, etc.)
   - Enviar notificaciones
   - Crear tareas de seguimiento

## Configuración en la Aplicación

### 1. Agregar Webhook URL al Webinar

1. Ve al panel de administración (`/admin`)
2. Edita el webinar que deseas conectar con Clay
3. En el campo "Clay Webhook URL", pega la URL del webhook que copiaste de Clay
4. Guarda los cambios

### 2. Estructura de Datos Enviados

Cuando un usuario se registra en el webinar, se enviarán los siguientes datos a Clay:

```json
{
  "timestamp": "2026-02-13T15:30:00Z",
  "email": "usuario@ejemplo.com",
  "first_name": "Juan",
  "last_name": "Pérez",
  "phone": "+1234567890",
  "company": "Empresa XYZ",
  "job_title": "Director de Marketing",
  "country": "México",
  "state": "CDMX",
  "city": "Ciudad de México",
  "submission_data": {
  },
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "webinar-lanzamiento",
  "utm_term": null,
  "utm_content": null,
  "webinar_title": "Título del Webinar",
  "client_name": "Nombre del Cliente"
}
```

### 3. Mapeo de Campos

El servicio de Clay mapea automáticamente los siguientes campos comunes:

| Campo Estándar | Posibles Nombres en Formulario |
|----------------|--------------------------------|
| email          | email, correo, e-mail          |
| first_name     | first_name, nombre, firstname  |
| last_name      | last_name, apellido, lastname  |
| phone          | phone, telefono, tel, celular  |
| company        | company, empresa, organization |
| job_title      | job_title, cargo, position     |
| country        | country, pais                  |
| state          | state, estado, region          |
| city           | city, ciudad                   |

## Logs y Debugging

La integración registra eventos en los logs de Laravel. Para ver si los datos se están enviando correctamente:

```bash
tail -f storage/logs/laravel.log | grep Clay
```

Verás mensajes como:
- `Clay: Lead sent successfully` - El lead se envió correctamente
- `Clay: Failed to send lead` - Hubo un error al enviar el lead
- `Clay: Webhook URL not configured` - No hay URL configurada

## Consideraciones

### Privacidad y GDPR

- Asegúrate de incluir en tu política de privacidad que los datos serán procesados por terceros (Clay)
- Los datos se envían de forma segura mediante HTTPS
- Clay debe cumplir con las regulaciones de privacidad aplicables

### Timing

- Los datos se envían de forma asíncrona después de que el usuario se registra
- Si Clay está caído o responde lentamente, no afectará la experiencia del usuario
- El registro se guardará en la base de datos independientemente del estado de Clay

### Troubleshooting

**Problema**: Los datos no llegan a Clay

Soluciones:
1. Verifica que la URL del webhook esté correctamente configurada
2. Revisa los logs de Laravel para ver errores específicos
3. Verifica en Clay que el webhook esté activo y configurado correctamente
4. Prueba la URL del webhook manualmente con una herramienta como Postman

**Problema**: Faltan campos en Clay

Soluciones:
1. Verifica que los nombres de los campos en tu formulario coincidan con el mapeo esperado
2. Revisa el objeto `submission_data` que contiene todos los datos originales
3. Ajusta tu workflow de Clay para extraer datos del objeto `submission_data`

## Ejemplo de Uso

1. Usuario se registra en el webinar con su email y nombre
2. Los datos se guardan en la base de datos
3. Automáticamente se envían a Clay
4. Clay enriquece los datos:
   - Busca el perfil de LinkedIn del usuario
   - Obtiene información de la empresa
   - Agrega datos de contacto adicionales
5. Clay puede automáticamente:
   - Crear o actualizar el contacto en tu CRM
   - Enviar una notificación a tu equipo de ventas
   - Agregar el contacto a una secuencia de emails

## Referencias

- [Documentación de Clay Webhooks](https://www.clay.com/docs/webhooks)
- [Clay Enrichment Options](https://www.clay.com/enrichments)
