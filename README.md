# 🍃 Take No Prisoners Flat-File v1.0

**Take No Prisoners Flat-File** es un sistema de gestión de contenidos (CMS) moderno y minimalista, **concebido para operar íntegramente sin bases de datos**. Al utilizar el sistema de archivos como motor principal, ofrece una velocidad de respuesta excepcional y una portabilidad total: basta con copiar la carpeta en cualquier servidor PHP para que el sitio cobre vida.

**Take No Prisoners Flat-File** tiene un panel de administración pensado para ser usado en una instalación local, o en producción *at-your-won-risk*. El plan óptimo es usar admin/ en local y actualizar la carpeta content/ remota a través de git, sftp o sistema similar.

## ✨ Funcionalidades clave

- **Arquitectura flat-file:** Todo el contenido reside en archivos `.md` dentro de la carpeta `/content`. No requiere base de datos.
- **Sistema de snippets dinámicos:** Inyecta lógica PHP o fragmentos HTML directamente en tus archivos Markdown usando la sintaxis `{{nombre_archivo}}`.
- **Búsqueda optimizada por índice:** Utiliza un índice JSON pre-renderizado para ofrecer resultados instantáneos sin consultar el disco en cada petición.
- **Escaneo de respaldo:** Capaz de rastrear archivos `.md` en tiempo real si el índice no está disponible.
- **Soporte multi-idioma nativo**: Detección automática de idioma por URL (ej. `/es/hola` vs `/en/hello`).
- **SEO Ready**: Generador de sitemap XML automático y gestión de metadatos mediante front matter.
- **Borradores protegidos**: Sistema de previsualización de archivos mediante tokens de acceso.

## 🚀 Instalación con ~~Docker~~ Podman

**Take No Prisoners Flat-File** está totalmente preparado para funcionar en contenedores. Para levantar tu instancia local en segundos, si ya tienes Podman instalado en tu máquina, sigue estos pasos:

1. **Clona este repositorio** en tu máquina local.
2. Como ves, ya incluye los archivos `Dockerfile` y `docker-compose.yml` en la raíz del proyecto.
3. **Ejecuta el despliegue desde la terminal:** `podman compose up`.
4. **Accede al sitio a través de tu navegador:** http://localhost:8080

## 🪾 Estructura del proyecto

Para que **Take No Prisoners Flat-File** funcione correctamente, asegúrate de mantener esta jerarquía:

```
.
├── admin/               # Archivo para administrar contenidos (admin | 1234)
├── assets/              # Recursos estáticos (CSS, JS, imágenes)
├── core/                # Núcleo: Content, Search, Helpers, Request, Indexer
├── content/             # Archivos .md (organizados por /es y /en)
├── includes/            # Plantillas (header/footer/search) y librerías (Parsedown)
├── media/               # Imágenes para las publicaciones
├── snippets/            # Fragmentos de código reutilizables
├── index.php            # Punto de entrada único
├── config.php           # Configuración del sitio
├── .htaccess            # Reglas de Apache
├── sitemap.php          # Generador del sitemap XML
└── docker-compose.yml   # Configuración de Docker
```

## 🧩 Gestión de componentes inteligentes (Inyección de assets)

**Take No Prisoners Flat-File** permite que los snippets funcionen como componentes autónomos. Puedes definir estilos CSS o scripts JavaScript dentro de un snippet y el motor los inyectará automáticamente en el lugar correcto del layout (`<head>` o final del `<body>`).

¿Cómo utilizarlo?

```html
<x-header>
    <link rel="stylesheet" href="/assets/css/componente.css">
    <style>.mi-clase { color: red; }</style>
</x-header>

<div class="mi-clase">
    Este es el contenido principal del snippet.
</div>

<x-footer>
    <script src="/assets/js/componente.js"></script>
    <script>console.log('Componente cargado');</script>
</x-footer>
```

## 🧩 Creación y actualización del archivo content/search_index.json

Ejecuta el script con `tudominio.com` `/core/indexer.php?token=TU_TOKEN_SECRETO`

Para tener tu propio token, edita el archivo core/indexer.php y en la línea:
```php
$secretToken = 'TU_TOKEN_SECRETO';
```
pon la palabra clave que quieras en lugar de `TU_TOKEN_SECRETO`.

## 🔐 Tema permisos + Docker

Si estás ejecutando localmente **Take No Prisoners Flat-File** en Docker es posible que tengas problemas de permisos para ejecutar el script `localhost:8080/core/indexer.php?token=TU_TOKEN_SECRETO`. Como solución simple, dale permiso a todo el mundo para escribir en la carpeta `content` y listo (`chmod -R 777 content/`).

## 🛡️ Seguridad

**Take No Prisoners Flat-File** incluye una capa de limpieza de datos en todas las peticiones y protege las vistas previas de borradores mediante tokens específicos definidos en el front matter de cada archivo.

## Panel de administración

Puedes usar el directorio admin/ que usa un archivo .htpasswd para solicitar unas credenciales de acceso o renombrar el index.php dentro de admin/ como admin.php (o el nombre que quieras) y llevarlo al primer nivel. A mí, para trabajar en local, me resulta más cómodo el segundo sistema.

## 🤟 Agradecimientos

- Proyecto desarrollado en un viejo [Notebook Acer Travelmate B115M] de 2014 y una [Raspberry Pi 5].
- [Coded with Megadeth as background music].


[Coded with Megadeth as background music]: https://www.youtube.com/watch?v=l6x_OhDKivA
[Notebook Acer Travelmate B115M]: https://www.xataka.com/ordenadores/un-portatil-sin-ventiladores-es-posible-acer-travelmate-b115
[Raspberry Pi 5]: https://www.raspberrypi.com/products/raspberry-pi-5/
