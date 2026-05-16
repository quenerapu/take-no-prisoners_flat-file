# TAKE NO PRISONERS — Flat-File CMS

CMS sin base de datos escrito en PHP puro. El contenido vive como archivos Markdown en `/content/{lang}/`. No requiere instalación, migraciones ni servidor de base de datos: basta con copiar la carpeta a cualquier servidor con PHP 8.3 y Apache.

---

## Tabla de contenidos

- [Características](#características)
- [Stack y dependencias](#stack-y-dependencias)
- [Inicio rápido](#inicio-rápido)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Arquitectura](#arquitectura)
- [Enrutamiento](#enrutamiento)
- [Formato de contenido](#formato-de-contenido-markdown--front-matter)
- [Sistema de snippets](#sistema-de-snippets)
- [Búsqueda](#búsqueda)
- [Panel de administración](#panel-de-administración)
- [Seguridad](#seguridad)
- [Docker / Podman](#docker--podman)
- [Despliegue en producción](#despliegue-en-producción)
- [Convenciones de código](#convenciones-de-código)

---

## Características

- **Sin base de datos.** Todo el contenido son archivos `.md` en el filesystem.
- **Multiidioma.** Rutas por prefijo de idioma (`/es/...`, `/en/...`) con fallback al header `Accept-Language`.
- **Búsqueda en dos niveles.** Índice JSON pre-generado + escaneo en tiempo real como fallback.
- **Sistema de snippets.** Fragmentos reutilizables (`.php`, `.md`, `.html`) con inyección de assets en `<head>` y `<body>`.
- **Páginas draft.** Ocultadas del público y del índice de búsqueda; accesibles con un token de previsualización.
- **Panel CRUD completo.** Editor EasyMDE, árbol de archivos, gestión de imágenes, conversión archivo↔carpeta.
- **SEO listo.** Sitemap XML, meta tags por página, fechas formateadas por idioma.
- **Portátil.** Funciona en cualquier hosting PHP compartido o servidor Apache.

---

## Stack y dependencias

| Componente | Versión |
|---|---|
| PHP | 8.3+ |
| Apache | 2.4 (`mod_rewrite`, `mod_ssl`, `mod_headers`) |
| [Parsedown](https://parsedown.org/) | Incluido en `includes/libs/` |
| [EasyMDE](https://easymde.tk/) | CDN (admin) |
| [Prism.js](https://prismjs.com/) | CDN (frontend) |
| [Font Awesome](https://fontawesome.com/) | 6.6, CDN (admin) |

**Extensiones PHP necesarias:** `mbstring`, `gd`, `zip`, `intl`, `exif`, `imagick` (incluidas en la imagen Docker).

No hay Composer, ni npm, ni build step.

---

## Inicio rápido

### Con Docker / Podman

```bash
# Clonar
git clone <repo> && cd take-no-prisoners_flat-file

# Levantar
podman compose up          # o: docker compose up

# Acceso web
http://localhost:8080

# Panel de administración
http://localhost:8080/admin/
# Usuario: admin  |  Contraseña: 1234
```

### En servidor PHP local

```bash
php -S localhost:8080
# Navegar a http://localhost:8080
```

> Para que el enrutamiento funcione con el servidor built-in de PHP se necesita que
> `index.php` reciba todas las peticiones. Con Apache basta el `.htaccess` incluido.

### Regenerar el índice de búsqueda

```bash
curl "http://localhost:8080/core/indexer.php?token=TU_TOKEN_SECRETO"
```

> Cambiar el token por defecto en `core/indexer.php` línea ~12 antes de exponer en producción.

---

## Estructura del proyecto

```
.
├── index.php               # Front controller (v5.7) — único punto de entrada
├── config.php              # Configuración global (idiomas, metadatos, fechas)
├── sitemap.php             # Generador de XML sitemap
├── server.sh               # Wrapper para docker compose
│
├── core/
│   ├── Content.php         # Parser Markdown + front matter + snippets
│   ├── Request.php         # Sanitización de GET/POST
│   ├── Search.php          # Motor de búsqueda (índice JSON + fallback)
│   ├── Helpers.php         # Utilidades (slugs, imágenes, snippets)
│   └── indexer.php         # Regenerador del índice (protegido por token)
│
├── includes/
│   ├── header.php          # Layout: cabecera HTML, nav, barra de búsqueda
│   ├── footer.php          # Layout: pie de página, inyección de Prism.js
│   ├── search.php          # Página de resultados de búsqueda
│   └── libs/
│       ├── Parsedown.php           # Librería Markdown
│       └── ExtensionParsedown.php  # Extensión personalizada (links, tablas)
│
├── admin/
│   ├── index.php           # Panel CRUD completo (EasyMDE, árbol, imágenes)
│   └── .htaccess           # HTTP Basic Auth
│
├── content/
│   ├── es/                 # Contenido en español (.md + front matter)
│   ├── en/                 # Contenido en inglés
│   └── search_index.json   # Índice de búsqueda pre-generado
│
├── snippets/               # Fragmentos reutilizables (.php, .md, .html)
│
├── assets/
│   └── css/
│       └── style.css       # Hoja de estilos principal
│
├── .htaccess               # Rewrite rules + cabeceras de caché
├── Dockerfile
├── docker-compose.yml
└── .podman/
    └── vhost.conf          # VirtualHost HTTP + HTTPS
```

---

## Arquitectura

### Front Controller

`index.php` recibe **todas** las peticiones (via `.htaccess`). Su flujo:

1. Carga `config.php` y las clases del `core/`.
2. Detecta el idioma desde el primer segmento de la URL; si no coincide, redirige según `Accept-Language`.
3. Resuelve la ruta a un archivo `.md` (ver tabla de enrutamiento).
4. Comprueba si la página es draft y verifica el token si aplica.
5. Parsea el contenido con `Core\Content`.
6. Renderiza el HTML con los templates de `includes/`.

### Clases del core

| Clase | Responsabilidad |
|---|---|
| `Core\Content` | Parsea front matter YAML, procesa snippets `{{nombre}}`, reemplaza `§TITLE`/`§DATE`, convierte Markdown a HTML, extrae bloques `<x-header>` / `<x-footer>` |
| `Core\Request` | Wrapper sobre `$_GET` / `$_POST` con `htmlspecialchars` + UTF-8 |
| `Core\Search` | Búsqueda en `search_index.json`; fallback a escaneo del filesystem |
| `Core\Helpers` | `cleanFilename()`, `getImagesInDir()`, `renderSystemSnippet()`, `cleanEmptyFolders()` |

### ExtensionParsedown

Extiende Parsedown con:
- Validación de links internos (marca como `.link-missing` si el archivo no existe).
- Resolución doble de rutas relativas (archivo plano vs. carpeta con `home.md`).
- Normalización de tablas Markdown sin separador de cabecera.

---

## Enrutamiento

| URL solicitada | Archivo resuelto |
|---|---|
| `/` | `content/{lang}/home.md` |
| `/es/sobre` | `content/es/sobre.md` |
| `/es/docs/inicio` | `content/es/docs/inicio.md` |
| `/es/docs/inicio/` *(trailing slash)* | `content/es/docs/inicio/home.md` |
| `/search` | `includes/search.php` |
| `/sitemap.xml` | `sitemap.php` |

**Detección de idioma:**
1. Primer segmento de la URL (`/es/...` → `es`).
2. Si no hay segmento de idioma, se lee `Accept-Language` del navegador y se redirige.

**Páginas de carpeta:** Una ruta que termina en `/` sirve el `home.md` de ese subdirectorio. El front controller redirige automáticamente añadiendo el trailing slash cuando detecta que existe la carpeta.

---

## Formato de contenido (Markdown + Front Matter)

```markdown
---
Title: Título de la página
Description: Meta descripción para SEO
Date: 2026-01-23
Draft: false
Draft-Token: token_preview_opcional
---

# §TITLE

Contenido normal en Markdown.

{{breadcrumb.php}}

§DATE
```

### Variables mágicas

| Marcador | Se reemplaza por |
|---|---|
| `§TITLE` | `meta['title']` del front matter |
| `§DATE` | Fecha formateada según el idioma (closure en `config.php`) |

### Draft pages

- `Draft: true` → oculta la página al público y la excluye del índice de búsqueda y del sitemap.
- `Draft-Token: mi_token` → permite previsualizar con `?draft=mi_token` en la URL.

### Imágenes con clases CSS

```markdown
![alt text](imagen.jpg#.clase-uno.clase-dos)
```

Implementado en `includes/libs/ExtensionParsedown.php`.

---

## Sistema de snippets

Los snippets son fragmentos reutilizables almacenados en `/snippets/`. Soportan tres formatos: `.php`, `.md` y `.html`.

**Inclusión en contenido:**

```markdown
{{nombre_snippet}}
{{breadcrumb.php}}
```

### Inyección de assets

Los snippets `.php` pueden declarar assets con etiquetas especiales que el motor extrae e inyecta en el lugar correcto del HTML:

```html
<x-header>
    <link rel="stylesheet" href="/assets/custom.css">
</x-header>

<div>contenido del snippet</div>

<x-footer>
    <script src="/assets/app.js"></script>
</x-footer>
```

- `<x-header>` → inyectado dentro de `<head>`
- `<x-footer>` → inyectado antes de `</body>`

**Nesting:** Los snippets pueden incluir otros snippets hasta 5 niveles de profundidad (protección contra recursión infinita).

---

## Búsqueda

La búsqueda opera en dos niveles:

1. **Rápido:** Lee `content/search_index.json` (pre-generado por `core/indexer.php`).
2. **Fallback:** Si el índice no existe, escanea todos los `.md` del filesystem en tiempo real.

El índice excluye automáticamente las páginas con `Draft: true`.

**Regenerar el índice** tras añadir o modificar contenido:

```bash
curl "https://tu-dominio.com/core/indexer.php?token=TU_TOKEN_SECRETO"
```

**Mínimo de caracteres para buscar:** 2.

---

## Panel de administración

El panel puede usarse de dos formas equivalentes:

- **`admin/index.php`** — Carpeta `admin/` en la raíz, protegida por HTTP Basic Auth (`.htpasswd`).
- **`admin.php`** — Archivo suelto en la raíz (preferido para uso en local, sin autenticación adicional).

En local se prefiere `admin.php`; en producción, la carpeta `admin/` con Basic Auth.

**Credenciales por defecto:** `admin` / `1234` — **cambiar en producción.**

### Funcionalidades

- Árbol de archivos/carpetas con navegación y drag-drop.
- Editor Markdown con EasyMDE (autosave en localStorage).
- Gestión de imágenes y media (upload, borrado).
- Crear, renombrar, mover y eliminar archivos y carpetas.
- Convertir un archivo `.md` en carpeta con `home.md` y viceversa.
- Picker de caracteres especiales (§, ¶, †, ‡, ©, …).
- Regeneración del índice de búsqueda desde el propio panel.
- Las líneas `Draft:` se destacan en rojo en el editor.

---

## Seguridad

| Mecanismo | Descripción |
|---|---|
| `Core\Request` | Toda entrada de usuario pasa por `htmlspecialchars(ENT_QUOTES, 'UTF-8')` |
| Path traversal | Rutas de archivo validadas y `..` eliminados en operaciones de lectura/escritura |
| Token del indexer | `core/indexer.php` requiere `?token=` que debe coincidir con el valor configurado |
| Draft token | Los borradores requieren `?draft=<token>` para visualizarse |
| HTTP Basic Auth | El panel `admin/` está protegido por `.htaccess` + `.htpasswd` en producción |
| Error reporting | Desactivado en producción (solo activo en `localhost` / `127.x`) |

**Acciones requeridas antes de producción:**

1. Cambiar el token del indexer en `core/indexer.php` línea ~12.
2. Cambiar las credenciales del panel en `admin/.htpasswd`.
3. Actualizar `base_url` y metadatos del sitio en `config.php`.

---

## Docker / Podman

```yaml
# docker-compose.yml (resumen)
services:
  web:
    build: .
    ports:
      - "8080:8080"   # HTTP
      - "8443:8443"   # HTTPS
    volumes:
      - ./:/var/www/html:Z
      - ./certs:/etc/apache2/certs:Z
```

**Imagen base:** `php:8.3-apache`

**Extensiones PHP instaladas en el contenedor:**
`xdebug`, `mbstring`, `gd`, `zip`, `intl`, `exif`, `imagick`

**Módulos Apache habilitados:** `rewrite`, `ssl`

**UID/GID dinámico:** El Dockerfile mapea `www-data` al UID/GID del host para evitar problemas de permisos en los volúmenes.

Si el indexer no puede escribir `search_index.json`:

```bash
chmod -R 777 content/
```

### Certificados SSL locales

Los certificados para `localhost` se esperan en `certs/`:

```
certs/
  localhost+2.pem
  localhost+2-key.pem
```

Generarlos con [mkcert](https://github.com/FiloSottile/mkcert):

```bash
mkcert localhost 127.0.0.1 ::1
mv localhost+2*.pem certs/
```

---

## Despliegue en producción

### Hosting PHP tradicional

1. Copiar todos los archivos al `DocumentRoot`.
2. Asegurarse de que `mod_rewrite` está activo y `AllowOverride All`.
3. Dar permisos de escritura a `content/`: `chmod -R 755 content/`
4. Actualizar `config.php` con el dominio y los metadatos reales.
5. Cambiar las credenciales del admin en `admin/.htpasswd`.
6. Cambiar el token del indexer y ejecutarlo:

```bash
curl "https://tu-dominio.com/core/indexer.php?token=NUEVO_TOKEN"
```

### Workflow Git recomendado

```
1. Editar contenido en local con admin.php
2. git add content/ && git commit -m "..."
3. git push
4. En producción: git pull
5. Regenerar índice con curl
```

---

## Convenciones de código

- **Namespace:** Todas las clases del core usan `namespace Core;`.
- **Sanitización:** Usar siempre `Core\Request::get()` / `Core\Request::post()` — nunca `$_GET` / `$_POST` directamente.
- **Configuración:** Toda configuración global va en `config.php` (devuelve array asociativo). No hay variables globales dispersas.
- **Carga de clases:** `require_once` explícito. No hay autoloader PSR-4.
- **Output:** Todo output dinámico debe pasar por `htmlspecialchars()`.
- **Rutas de archivo:** Validar siempre contra directory traversal antes de leer o escribir.
- **Sin tests, sin build:** No existe suite de tests ni paso de compilación de assets.

---

## `config.php` — referencia

```php
<?php
return [
    'app_name'    => 'Take no prisoners',
    'app_version' => '1.0.0',
    'base_url'    => 'https://tu-dominio.com',   // Sin trailing slash
    'name'        => 'Take no prisoners',
    'description' => 'Descripción del sitio.',
    'author'      => 'Tu nombre',
    'twitter'     => '@tu-usuario',
    'default_img' => '/assets/default-share.jpg',
    'cache_enabled' => false,
    'languages'   => [
        'es' => [
            'name' => 'Español',
            'date' => function($ts) {
                $meses = ['','Enero','Febrero',...,'Diciembre'];
                return date('j', $ts) . ' de ' . $meses[date('n', $ts)] . ' de ' . date('Y', $ts);
            }
        ],
        'en' => [
            'name' => 'English',
            'date' => function($ts) { return date('F j, Y', $ts); }
        ],
    ]
];
```

Para añadir un idioma, agregar una entrada al array `languages` con su `name` y su closure `date`.

---

## Limitaciones conocidas

- El rendimiento degrada con volúmenes muy grandes de contenido (200+ páginas) ya que no hay caché de parseo.
- El índice de búsqueda debe regenerarse manualmente tras cada cambio de contenido.
- No hay historial de versiones ni rollback de contenido (se delega en Git).
- Un único nivel de autenticación para el admin (sin roles ni multi-usuario).
- Las imágenes no se optimizan ni redimensionan automáticamente.

---

## Licencia

Ver [LICENSE](LICENSE).
