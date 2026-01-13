# Troubleshooting - Subdominios de Webinars

## Problema: El .htaccess no funciona

### Checklist de Diagnóstico

#### 1. Verificar que existe un cliente en la base de datos

```bash
# Conectar a la base de datos y verificar
SELECT id, name, slug FROM clients WHERE slug = 'escala';
```

**Debe retornar:**
- Un registro con `slug = 'escala'`
- Si no existe, créalo en `/admin/clients`

---

#### 2. Verificar que existe un webinar de prueba

```bash
SELECT id, title, slug, client_id FROM webinars WHERE client_id = [ID_DEL_CLIENTE];
```

**Debe retornar:**
- Al menos un webinar asociado al cliente
- Si no existe, créalo en `/admin/webinars`

---

#### 3. Probar la URL directamente en webinars.templet.io

**Probar primero SIN el proxy:**
```
https://webinars.templet.io/webinars/{slug-del-webinar}
```

**Resultado esperado:**
- ❌ Error 404 "Cliente no encontrado" (porque no detecta el subdominio)
- ✅ Esto confirma que la app Laravel funciona

---

#### 4. Verificar el .htaccess en el servidor

**Vía FTP/SSH, verificar:**
```bash
# Ubicación del archivo
/public_html/escala/.htaccess

# Verificar que las reglas están ANTES de "# BEGIN WordPress"
```

**Contenido esperado:**
```apache
# === PROXY PARA WEBINARS - Templet ===
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/webinars/
    RewriteRule ^webinars/(.*)$ https://webinars.templet.io/webinars/$1 [P,L,QSA]
</IfModule>

# BEGIN WordPress
```

---

#### 5. Verificar que mod_proxy está habilitado

**El proxy requiere `mod_proxy` en Apache.**

**Síntoma si NO está habilitado:**
- Error 500 Internal Server Error
- En logs de Apache: "Invalid command 'ProxyPass'"

**Solución:**
- Contactar a Hostinger para habilitar `mod_proxy`
- O usar alternativa con RewriteRule sin flag [P]

---

#### 6. Probar con curl (diagnóstico avanzado)

```bash
# Probar el proxy desde línea de comandos
curl -I https://escala.templet.io/webinars/test-webinar

# Verificar headers de respuesta
# Debe retornar 200 o 404, NO 301/302
```

---

#### 7. Revisar logs del servidor

**Logs de Apache:**
```bash
tail -f /var/log/apache2/error.log
# o
tail -f /home/usuario/logs/error.log
```

**Buscar:**
- Errores de mod_proxy
- Errores de RewriteRule
- Errores 500

---

## Soluciones Alternativas

### Alternativa 1: Si mod_proxy NO está disponible

Si Hostinger no tiene `mod_proxy` habilitado, usa **redirect** en lugar de proxy:

```apache
# === REDIRECT PARA WEBINARS - Templet ===
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} ^/webinars/
    RewriteRule ^webinars/(.*)$ https://webinars.templet.io/webinars/$1 [R=301,L,QSA]
</IfModule>
```

**Desventaja:**
- ❌ La URL cambiará en el navegador a `webinars.templet.io`
- ✅ Pero al menos funcionará

---

### Alternativa 2: Usar PHP para proxy

Si Apache no soporta proxy, crear un archivo PHP que haga el proxy:

```php
<?php
// /public_html/escala/webinars/index.php

$path = $_SERVER['REQUEST_URI'];
$query = $_SERVER['QUERY_STRING'];

$url = "https://webinars.templet.io" . $path;
if ($query) {
    $url .= "?" . $query;
}

// Hacer proxy
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
```

---

## Casos Comunes

### Caso 1: Error 404 en escala.templet.io/webinars/test

**Causa:** No existe el cliente o webinar en la base de datos

**Solución:**
1. Ir a `/admin/clients` y crear cliente con `slug = 'escala'`
2. Ir a `/admin/webinars` y crear webinar de prueba

---

### Caso 2: Error 500 Internal Server Error

**Causa:** mod_proxy no está habilitado o error en .htaccess

**Solución:**
1. Verificar sintaxis del .htaccess
2. Contactar a Hostinger para habilitar mod_proxy
3. Usar Alternativa 1 (redirect) temporalmente

---

### Caso 3: La URL cambia a webinars.templet.io

**Causa:** Estás usando `[R]` en lugar de `[P]` en el RewriteRule

**Solución:**
Verificar que el flag sea `[P,L,QSA]` no `[R,L,QSA]`

---

### Caso 4: Los parámetros UTM no se guardan

**Causa:** Falta la flag `QSA` en el RewriteRule

**Solución:**
Agregar `QSA` al final: `[P,L,QSA]`

---

## Comandos Útiles

### Ver configuración de Apache
```bash
apache2ctl -M | grep proxy
# Debe mostrar: proxy_module
```

### Probar .htaccess localmente
```bash
# Verificar sintaxis
apachectl configtest
```

### Ver logs en tiempo real
```bash
tail -f storage/logs/laravel.log | grep "Client detected"
```
