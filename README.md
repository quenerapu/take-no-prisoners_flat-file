# 🍃 Take No Prisoners Flat-File v1.0

**Take No Prisoners Flat-File** es un sistema de gestión de contenidos (CMS) moderno y minimalista, **concebido para operar íntegramente sin bases de datos**. Al utilizar el sistema de archivos como motor principal, ofrece una velocidad de respuesta excepcional y una portabilidad total: basta con copiar la carpeta en cualquier servidor PHP para que el sitio cobre vida.

## ✨ Funcionalidades Clave

- **Arquitectura Flat-File:** Todo el contenido reside en archivos `.md` dentro de la carpeta `/content`. No requiere base de datos.
- **Sistema de Snippets Dinámicos:** Inyecta lógica PHP o fragmentos HTML directamente en tus archivos Markdown usando la sintaxis `{{nombre_archivo}}`.
- **Búsqueda optimizada por índice:** Utiliza un índice JSON pre-renderizado para ofrecer resultados instantáneos sin consultar el disco en cada petición.
- **Escaneo de respaldo:** Capaz de rastrear archivos `.md` en tiempo real si el índice no está disponible.
- **Soporte multi-idioma nativo**: Detección automática de idioma por URL (ej. `/es/hola` vs `/en/hello`).
- **SEO Ready**: Generador de sitemap XML automático y gestión de metadatos mediante front matter.
- **Borradores protegidos**: Sistema de previsualización de archivos mediante tokens de acceso.

## 🚀 Instalación con Docker

**Take No Prisoners Flat-File** está totalmente preparado para funcionar en contenedores. Para levantar tu instancia local en segundos, sigue estos pasos:

1. **Clona este repositorio** en tu máquina local.
2. **Crea los archivos de configuración** (Dockerfile y docker-compose.yml) en la raíz del proyecto.

Dockerfile:

```bash
# Usamos PHP 8.2 con Apache
FROM php:8.2-apache

# Activamos el módulo rewrite de Apache para gestionar las URLs amigables del .htaccess
RUN a2enmod rewrite

# Instalamos dependencias para el procesamiento de texto (necesario para mbstring e intl)
RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install intl

# Copiamos el código fuente al contenedor
COPY . /var/www/html/

# Ajustamos permisos para que el servidor pueda generar el índice JSON y el sitemap
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
```

docker-compose.yml

```bash
version: '3.8'

services:
  grijander:
    build: .
    container_name: grijander_cms
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
    restart: always
```

3. **Ejecuta el despliegue desde la terminal:** `docker-compose up -d`
4. **Accede al sitio a través de tu navegador:** http://localhost:8080

## 🪾 Estructura del Proyecto

Para que el proyecto funcione correctamente, asegúrate de mantener esta jerarquía:

```
.
├── core/                # Núcleo: Content, Search, Helpers, Request, Indexer
├── content/             # Archivos .md (organizados por /es y /en)
├── includes/            # Plantillas (header/footer/search) y librerías (Parsedown)
├── snippets/            # Fragmentos de código reutilizables
├── assets/              # Recursos estáticos (CSS, JS, imágenes)
├── index.php            # Punto de entrada único
├── config.php           # Configuración del sitio
├── .htaccess            # Reglas de Apache
├── sitemap.php          # Generador del sitemap XML
└── docker-compose.yml   # Configuración de Docker
```
🧩 Gestión de Componentes Inteligentes (Inyección de Assets)

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

## 🛡️ Seguridad

**Take No Prisoners Flat-File** incluye una capa de limpieza de datos en todas las peticiones y protege las vistas previas de borradores mediante tokens específicos definidos en el front matter de cada archivo.
