# CLAUDE.md — Take No Prisoners Flat-File CMS

## Project Overview

Flat-file CMS sin base de datos. El contenido se almacena como archivos Markdown en `/content/{lang}/`. Diseñado para funcionar en cualquier servidor PHP copiando la carpeta.

- **Stack:** PHP 8.3, Apache 2.4, Markdown (Parsedown), Docker/Podman
- **Versión:** 1.0 (front controller v5.7)
- **Sin base de datos:** puro filesystem + I/O de archivos

## Comandos

```bash
# Levantar entorno de desarrollo
podman compose up
# Acceso: http://localhost:8080
# Admin:  http://localhost:8080/admin/  (user: admin / pass: 1234)

# Regenerar índice de búsqueda (token en core/indexer.php línea 9)
curl "http://localhost:8080/core/indexer.php?token=TU_TOKEN_SECRETO"
```

No hay suite de tests. No hay build step para assets.

## Arquitectura

```
server.sh          # Disparador
index.php          # Front controller — único punto de entrada
config.php         # Configuración global (langs, metadata, cache)
sitemap.php        # Generador XML sitemap
core/
  Content.php      # Parser Markdown + front matter + snippets
  Request.php      # Sanitización GET/POST
  Search.php       # Motor de búsqueda (índice JSON + fallback)
  Helpers.php      # Utilidades (slugs, listado de imágenes, snippets)
  indexer.php      # Regenerador del índice (protegido por token)
includes/
  header.php       # Layout: cabecera HTML, nav, barra de búsqueda
  footer.php       # Layout: pie, inyección de Prism.js y snippets
  search.php       # Página de resultados de búsqueda
  libs/            # Parsedown + ExtensionParsedown
admin/
  index.php        # Panel CRUD completo (EasyMDE, árbol de archivos)
  .htaccess        # HTTP Basic Auth
content/
  es/              # Contenido en español (Markdown + front matter)
  en/              # Contenido en inglés
  search_index.json
snippets/          # Fragmentos reutilizables (.php, .md, .html)
assets/css/        # Hojas de estilo
```

## Enrutamiento

| URL            | Archivo                                            |
|----------------|----------------------------------------------------|
| `/`            | `content/{lang}/home.md`                           |
| `/es/sobre`    | `content/es/sobre.md` o `content/es/sobre/home.md` |
| `/search`      | `includes/search.php`                              |
| `/sitemap.xml` | `sitemap.php`                                      |


La lengua la determina el primer segmento de la URL. Fallback al header `Accept-Language`.

## Formato de contenido (Markdown + Front Matter)

```markdown
---
Title: Título de la página
Description: Meta descripción SEO
Date: 2026-01-23
Draft: false
Draft-Token: token_preview_opcional
---

# §TITLE

Contenido normal en Markdown.

{{breadcrumb.php}}

§DATE
```

- `§TITLE` → sustituido por `meta['title']`
- `§DATE` → fecha formateada según el idioma
- `{{nombre_snippet}}` → inyecta `/snippets/nombre_snippet` (.php, .md o .html)
- Los snippets soportan nesting hasta 5 niveles
- `Draft: true` oculta la página; accesible con `?draft=Draft-Token`

## Snippets y asset injection

Los snippets `.php` pueden declarar assets con etiquetas especiales:

```html
<x-header>
    <link rel="stylesheet" href="/assets/custom.css">
</x-header>

<div>contenido</div>

<x-footer>
    <script src="/assets/app.js"></script>
</x-footer>
```

El motor extrae estos bloques y los inyecta en `<head>` o antes de `</body>` respectivamente.

## Convenciones de código

- **Namespace:** Todas las clases del core usan `namespace Core;`
- **Sanitización:** Usar siempre `Core\Request::get()` / `Core\Request::post()` — nunca `$_GET`/`$_POST` directamente
- **Configuración:** Toda configuración global va en `config.php` (devuelve array asociativo)
- **Carga de clases:** `require_once` explícito (no hay autoloader PSR-4)
- **Seguridad:** Validar rutas de ficheros para prevenir directory traversal; usar `htmlspecialchars()` en output

## Extensión de Parsedown

Sintaxis especial para imágenes con clases CSS:

```markdown
![alt text](imagen.jpg#.mi-clase.otra-clase)
```

Implementado en `includes/libs/ExtensionParsedown.php`.

## Búsqueda

- **Rápido:** Lee `content/search_index.json` (pre-generado)
- **Fallback:** Escaneo en tiempo real de todos los `.md` si el índice no existe
- El índice excluye drafts automáticamente
- Regenerar tras añadir/modificar contenido

## Docker / Podman

- Imagen base: `php:8.3-apache`
- Extensiones PHP instaladas: xdebug, mbstring, gd, zip, intl, exif, imagick
- Módulos Apache: rewrite, ssl
- Puertos: `8080` (HTTP), `8443` (HTTPS)
- Volúmenes: raíz del proyecto + `certs/` para SSL

Si el indexer no puede escribir `search_index.json`:
```bash
chmod -R 777 content/
```

## Panel de administración

El panel puede usarse de dos formas equivalentes:

- **`admin/index.php`** — carpeta `admin/` en la raíz, protegida por HTTP Basic Auth (`admin/.htpasswd`)
- **`admin.php`** — archivo suelto en la raíz del proyecto (preferido para uso en local)

Cuando el usuario menciona `admin.php` se refiere indistintamente a cualquiera de las dos formas. En local se prefiere `admin.php` en la raíz; en producción, la carpeta `admin/` con Basic Auth.

- Credenciales por defecto: `admin` / `1234` — **cambiar en producción**
- Funciones: CRUD de archivos/carpetas, editor Markdown (EasyMDE), gestión de imágenes, regeneración del índice de búsqueda
