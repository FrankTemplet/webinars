# Estandarización de Campos de Formulario - Webinars

## Resumen

Se ha implementado una estandarización completa de los campos de formulario para los webinars, resolviendo el problema de inconsistencia entre `employee_range` y `number_employees`.

## Cambios Realizados

### 1. Campos por Defecto en Nuevos Webinars

Ahora cada webinar creado incluirá automáticamente los siguientes campos estándar:

```json
{
  "first_name": "string",
  "last_name": "string", 
  "email": "string",
  "phone_number": "string",
  "company": "string",
  "country": "string",
  "job_title": "string",
  "employee_range": "select (1-10, 11-50, 51-200, 201-500, 501-1000, 1000+)"
}
```

**Características:**
- ✅ Los campos se muestran pre-cargados al crear un nuevo webinar
- ✅ Se pueden agregar campos adicionales según necesidad
- ✅ Se pueden modificar o eliminar campos existentes
- ✅ El campo `employee_range` ahora es un select con opciones predefinidas

### 2. Compatibilidad con Datos Históricos

El widget de gráficas (`SubmissionsChartWidget`) ahora soporta ambos nombres de campo:
- `employee_range` (nuevo estándar)
- `number_employees` (legacy)

Esto asegura que las gráficas funcionen correctamente con todos los registros históricos.

### 3. Comando de Normalización

Se ha creado un comando Artisan para normalizar los datos existentes en la base de datos:

```bash
php artisan submissions:normalize-fields
```

**Este comando:**
- ✅ Busca todos los submissions con el campo `number_employees`
- ✅ Los renombra a `employee_range`
- ✅ Elimina el campo antiguo `number_employees`
- ✅ Muestra un reporte detallado de los cambios realizados

## Instrucciones de Migración

### Paso 1: Normalizar Datos Existentes

Ejecuta el siguiente comando en tu terminal:

```bash
php artisan submissions:normalize-fields
```

**Salida esperada:**
```
Starting normalization of submission fields...
✓ Updated submission ID: 1
✓ Updated submission ID: 5
✓ Updated submission ID: 12

Normalization complete!
┌─────────┬───────┐
│ Status  │ Count │
├─────────┼───────┤
│ Updated │ 15    │
│ Skipped │ 8     │
│ Total   │ 23    │
└─────────┴───────┘
```

### Paso 2: Verificar Webinars Existentes (Opcional)

Los webinars existentes mantendrán sus campos actuales. Si deseas actualizar algún webinar para usar los campos estándar:

1. Ve al panel de administración `/admin`
2. Edita el webinar deseado
3. En la sección "Form Builder", ajusta los campos según necesites
4. Puedes agregar el campo `employee_range` manualmente si no existe

### Paso 3: Crear Nuevos Webinars

Al crear un nuevo webinar:
1. Los campos estándar se cargarán automáticamente
2. Puedes agregar campos adicionales usando el botón "Add item"
3. Puedes eliminar campos que no necesites
4. Los cambios se guardarán en el `form_schema` del webinar

## Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `app/Filament/Resources/Webinars/Pages/CreateWebinar.php` | Agregado hook para establecer campos por defecto |
| `app/Filament/Resources/Webinars/Schemas/WebinarForm.php` | Agregado default en el repeater con campos estándar |
| `app/Filament/Widgets/SubmissionsChartWidget.php` | Soporte para ambos nombres de campo |
| `app/Console/Commands/NormalizeSubmissionFields.php` | Nuevo comando de normalización |

## Ventajas

✅ **Consistencia**: Todos los webinars nuevos tendrán la misma estructura base  
✅ **Flexibilidad**: Se pueden agregar campos personalizados cuando sea necesario  
✅ **Compatibilidad**: Los datos históricos siguen funcionando  
✅ **Mantenibilidad**: Fácil de actualizar campos estándar en el futuro  

## Notas Técnicas

- El campo `employee_range` ahora usa opciones predefinidas para evitar inconsistencias
- El widget usa el operador null coalescing (`??`) para buscar ambos campos
- La normalización es no destructiva: solo modifica datos donde `number_employees` existe
- Los campos por defecto solo se aplican en webinars nuevos (no modifica existentes)

## Soporte

Si encuentras algún problema o necesitas agregar más normalizaciones, el código del comando está en:
`app/Console/Commands/NormalizeSubmissionFields.php`

Puedes extender el método `handle()` para agregar más transformaciones según sea necesario.
