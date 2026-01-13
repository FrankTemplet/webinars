# ============================================================================
# PROXY PARA WEBINARS - Templet
# ============================================================================
# 
# INSTRUCCIONES DE INSTALACIÓN:
# 1. Abrir el .htaccess existente del sitio del cliente
# 2. Buscar la línea "# BEGIN WordPress"
# 3. Copiar el bloque de abajo JUSTO ANTES de "# BEGIN WordPress"
# 4. NO reemplazar el .htaccess completo, solo AGREGAR estas líneas
#
# IMPORTANTE: Estas reglas deben ir ANTES de las reglas de WordPress
# para que se procesen primero y no sean ignoradas.
# ============================================================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Redirigir solo /webinars/* a la aplicación Laravel
    # Mantiene todas las demás rutas del sitio del cliente intactas
    # 
    # Flags:
    #   P = Proxy (mantiene URL original en el navegador)
    #   L = Last (detiene procesamiento si coincide)
    #   QSA = Query String Append (preserva parámetros UTM)
    
    RewriteCond %{REQUEST_URI} ^/webinars/
    RewriteRule ^webinars/(.*)$ https://webinars.templet.io/webinars/$1 [P,L,QSA]
</IfModule>

# ============================================================================
# NOTAS TÉCNICAS:
# ============================================================================
# - El usuario ve: https://escala.templet.io/webinars/mi-webinar
# - Internamente sirve: https://webinars.templet.io/webinars/mi-webinar
# - El middleware DetectClientFromDomain detecta "escala" del dominio original
# - QSA preserva UTMs: ?utm_source=facebook&utm_campaign=2024
# - Compatible con LiteSpeed Cache (los UTMs se pasan a Laravel aunque se ignoren en caché)
# - Requiere mod_proxy habilitado en Apache (generalmente ya está en Hostinger)
# ============================================================================
