# Configuración de Subdominios por Cliente

## Resumen

Este documento explica cómo configurar un nuevo cliente para que sus webinars estén disponibles en `https://{cliente}.templet.io/webinars/{webinar}`.

## Requisitos Previos

- Acceso al panel de administración de Filament (`/admin`)
- Acceso al servidor vía FTP o SSH
- El sitio del cliente ya debe existir en su subdominio

## Proceso de Configuración

### 1. Crear Cliente en el Admin Panel

1. Acceder a: `https://webinars.templet.io/admin/clients`
2. Click en **"New Client"**
3. Completar:
   - **Name**: Nombre completo del cliente (ej: "Escala")
   - **Slug**: Identificador único en minúsculas sin espacios (ej: "escala")
     - ⚠️ **IMPORTANTE**: El slug debe coincidir exactamente con el subdominio
   - **Logo**: Subir el logo del cliente
4. Guardar

### 2. Configurar .htaccess en el Sitio del Cliente

#### Opción A: Vía FTP (Recomendado para Hostinger)

1. Conectar vía FTP al servidor
2. Navegar a la carpeta del cliente: `/public_html/escala/`
3. Buscar el archivo `.htaccess` existente
4. **Ubicar la sección `# BEGIN WordPress`**
5. **Agregar las reglas de proxy JUSTO ANTES** de `# BEGIN WordPress`:

```apache
# === PROXY PARA WEBINARS - Templet ===
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Redirigir /webinars/* a la aplicación Laravel
    # IMPORTANTE: Estas reglas deben ir ANTES de las reglas de WordPress
    # QSA preserva parámetros UTM y otros query strings
    RewriteCond %{REQUEST_URI} ^/webinars/
    RewriteRule ^webinars/(.*)$ https://webinars.templet.io/webinars/$1 [P,L,QSA]
</IfModule>

# BEGIN WordPress
# ... (reglas existentes de WordPress)
```

> [!CAUTION]
> **Ubicación crítica:** Las reglas deben ir **ANTES** de `# BEGIN WordPress` para que se procesen primero. Si las pones después, WordPress las ignorará.

> [!IMPORTANT]
> **Flags importantes:**
> - `P` = Proxy (mantiene la URL original en el navegador)
> - `L` = Last (detiene el procesamiento de reglas si coincide)
> - `QSA` = Query String Append (preserva parámetros UTM: `?utm_source=facebook&utm_campaign=2024`)

> [!WARNING]
> **LiteSpeed Cache:** Si el sitio tiene LiteSpeed Cache configurado con `CacheKeyModify -qs:utm*`, los parámetros UTM se ignorarán en el caché pero **SÍ** se pasarán a Laravel gracias a la flag `QSA`.

6. Guardar el archivo

#### Opción B: Vía SSH

```bash
# Conectar al servidor
ssh usuario@templet.io

# Navegar a la carpeta del cliente
cd /public_html/escala/

# Editar .htaccess
nano .htaccess

# Agregar las reglas de proxy al final del archivo
# Guardar: Ctrl+O, Enter, Ctrl+X
```

### 3. Verificar Configuración

1. Crear un webinar de prueba para el cliente en el admin
2. Visitar: `https://escala.templet.io/webinars/{slug-del-webinar}`
3. Verificar que:
   - La página carga correctamente
   - El logo del cliente se muestra
   - El formulario funciona
   - La URL en el navegador sigue siendo `escala.templet.io` (no cambia a `webinars.templet.io`)

## Troubleshooting

### Error 404 al acceder al webinar

**Causa**: El .htaccess no está configurado correctamente o mod_rewrite no está habilitado.

**Solución**:
1. Verificar que el archivo `.htaccess` existe en `/public_html/escala/`
2. Verificar que las reglas de rewrite están correctamente escritas
3. Contactar a Hostinger para verificar que `mod_proxy` está habilitado

### El cliente no se detecta (error "Cliente no encontrado")

**Causa**: El slug del cliente no coincide con el subdominio.

**Solución**:
1. Verificar en el admin que el slug del cliente es exactamente `escala` (sin mayúsculas, sin espacios)
2. Verificar que el subdominio es exactamente `escala.templet.io`

### La URL cambia a webinars.templet.io

**Causa**: Falta la flag `[P]` (proxy) en el RewriteRule, está usando redirect `[R]`.

**Solución**:
1. Verificar que el RewriteRule tiene `[P,L]` al final, no `[R,L]`
2. Ejemplo correcto: `RewriteRule ^webinars/(.*)$ https://webinars.templet.io/webinars/$1 [P,L]`

### El sitio del cliente deja de funcionar

**Causa**: Las reglas de rewrite están interfiriendo con las rutas existentes del sitio.

**Solución**:
1. Verificar que la condición `RewriteCond %{REQUEST_URI} ^/webinars/` está presente
2. Esta condición asegura que **solo** las rutas que empiezan con `/webinars/` se redirijan

## Notas Importantes

- ⚠️ **No eliminar** el `.htaccess` existente del cliente, solo **agregar** las reglas de proxy
- ⚠️ El proxy solo afecta rutas que empiezan con `/webinars/`, el resto del sitio funciona normal
- ⚠️ Requiere que `mod_proxy` esté habilitado en Apache (generalmente ya está en Hostinger)
- ✅ Cada cliente solo necesita configurarse **una vez**
- ✅ Después de configurado, todos los webinars se crean desde el admin sin tocar FTP

## Archivo de Referencia

El archivo `.htaccess.client-template` en la raíz del proyecto contiene el template completo que puedes copiar y pegar.
