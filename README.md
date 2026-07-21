# Cuba Exchange Rate Scraper

Un scraper ligero desarrollado en **PHP puro** que descarga automáticamente las tasas del **Mercado Informal de Divisas en Cuba** desde elTOQUE y las expone como una API JSON sencilla.

El proyecto descarga la página completa, localiza la sección:

```html
<div id="mercado-informal">
```

extrae la tabla de tasas y genera un JSON listo para consumir desde cualquier aplicación.

## Demo

API funcionando en vivo:

**[https://cubaexchangeratescraper.free.nf/](https://cubaexchangeratescraper.free.nf/)**

## Características

* ✅ PHP puro (sin Composer)
* ✅ Sin dependencias externas
* ✅ Configuración mediante `.env`
* ✅ Caché configurable
* ✅ Actualización automática
* ✅ JSON limpio y estructurado
* ✅ Manejo de errores
* ✅ Compatible con cualquier hosting PHP
* ✅ Protección del archivo `.env`
* ✅ Protección del caché
* ✅ CORS habilitado

---

# Estructura

```text
/
│── index.php
│── .env
│── .htaccess
│── README.md
│
└── storage/
      cache.json
```

---

# Requisitos

* PHP 7.4+
* Extensión cURL
* Extensión DOM/XML
* Apache (recomendado)

---

# Instalación

Sube todos los archivos al servidor.

## Hosting gratuito

Este proyecto puede alojarse fácilmente en cualquier servidor compatible con PHP 7.4 o superior.

Si deseas una opción gratuita, puedes utilizar **InfinityFree**, que ofrece hosting PHP sin costo.

Sitio web:

**https://www.infinityfree.com/**

El proyecto de demostración público está alojado en InfinityFree y puedes probarlo aquí:

**https://cubaexchangeratescraper.free.nf/**

Solo debes subir los archivos mediante el Administrador de Archivos o por FTP y el scraper estará listo para funcionar.

**Nota:** Para un mejor rendimiento y mayor estabilidad en producción, se recomienda utilizar un hosting de pago o un VPS.


No necesitas instalar Composer.

No necesitas ninguna librería.

El directorio **storage** se crea automáticamente si no existe.

---

# Configuración

Toda la configuración se realiza desde el archivo:

```text
.env
```

Ejemplo:

```env
SOURCE_URL=https://eltoque.com/tasas-de-cambio-cuba

CACHE_TIME=3600

REQUEST_TIMEOUT=30

CACHE_FILE=storage/cache.json

TIMEZONE=America/Havana

ALLOW_FORCE_REFRESH=false
```

---

# Configuración de variables

## SOURCE_URL

Página desde donde se obtienen las tasas.

```env
SOURCE_URL=https://eltoque.com/tasas-de-cambio-cuba
```

---

## CACHE_TIME

Tiempo del caché en segundos.

Ejemplos:

Cada minuto

```env
CACHE_TIME=60
```

Cada 5 minutos

```env
CACHE_TIME=300
```

Cada 15 minutos

```env
CACHE_TIME=900
```

Cada 30 minutos

```env
CACHE_TIME=1800
```

Cada hora

```env
CACHE_TIME=3600
```

Cada 6 horas

```env
CACHE_TIME=21600
```

---

## REQUEST_TIMEOUT

Tiempo máximo de espera para descargar la página.

```env
REQUEST_TIMEOUT=30
```

---

## CACHE_FILE

Ruta donde se almacenará el JSON.

```env
CACHE_FILE=storage/cache.json
```

---

## TIMEZONE

Zona horaria utilizada por PHP.

```env
TIMEZONE=America/Havana
```

---

## ALLOW_FORCE_REFRESH

Permite actualizar el caché manualmente.

```env
ALLOW_FORCE_REFRESH=true
```

Luego puedes abrir:

```text
index.php?refresh=1
```

---

# Funcionamiento

El proceso es muy sencillo:

1. Descarga la página completa de elTOQUE.
2. Busca la sección:

```html
<div id="mercado-informal">
```

3. Localiza la primera tabla dentro de esa sección.
4. Extrae todas las monedas.
5. Convierte la información en JSON.
6. Guarda el resultado en caché.
7. Devuelve el JSON.

Mientras el caché sea válido, no vuelve a descargar la página.

---

# Respuesta JSON

Ejemplo:

```json
{
    "success": true,
    "cached": false,
    "source": "https://eltoque.com/tasas-de-cambio-cuba",
    "section_id": "mercado-informal",
    "source_updated_at": "20 de julio de 2026 a las 10:13 p. m.",
    "scraped_at": "2026-07-20T22:20:00-04:00",
    "count": 7,
    "rates": [
        {
            "unit": 1,
            "currency": "USD",
            "price": 670,
            "currency_to": "CUP",
            "change": null,
            "direction": "unchanged",
            "display": "670.00 CUP"
        },
        {
            "unit": 1,
            "currency": "EUR",
            "price": 770,
            "currency_to": "CUP",
            "change": null,
            "direction": "unchanged",
            "display": "770.00 CUP"
        },
        {
            "unit": 1,
            "currency": "MLC",
            "price": 428.06,
            "currency_to": "CUP",
            "change": 1.95,
            "direction": "up",
            "display": "428.06 CUP"
        }
    ]
}
```

---

# Uso

Abrir desde el navegador:

```text
https://midominio.com/index.php
```

o simplemente:

```text
https://midominio.com/
```

si `index.php` es el archivo principal.

---

# Consumir desde JavaScript

```javascript
fetch("https://midominio.com/index.php")
    .then(r => r.json())
    .then(data => console.log(data));
```

---

# Consumir desde PHP

```php
$json = file_get_contents("https://midominio.com/index.php");

$data = json_decode($json, true);

print_r($data);
```

---

# Consumir desde cURL

```bash
curl https://midominio.com/index.php
```

---

# Caché

Mientras el caché no expire:

* no se descarga nuevamente la página
* se devuelve el último JSON almacenado

Cuando expire:

* descarga nuevamente el sitio
* genera un nuevo JSON
* reemplaza automáticamente el caché

---

# Actualización manual

Si en el `.env` tienes:

```env
ALLOW_FORCE_REFRESH=true
```

puedes actualizar inmediatamente:

```text
https://midominio.com/index.php?refresh=1
```

---

# Seguridad

El archivo `.htaccess` bloquea:

* `.env`
* `storage/`
* `cache.json`
* archivos ocultos
* backups
* logs
* archivos `.sql`
* archivos `.ini`

Por ejemplo:

```text
https://midominio.com/.env
```

debe devolver **403 Forbidden**.

---

# Solución de problemas

## Error de cURL

Verifica que esté instalada la extensión:

```bash
php -m | grep curl
```

---

## Error de DOMDocument

Verifica:

```bash
php -m | grep dom
```

o

```bash
php -m | grep xml
```

---

## Error escribiendo el caché

Asegúrate de que PHP tenga permisos sobre:

```text
storage/
```

---

## Devuelve `stale=true`

Significa que no fue posible actualizar la información desde el sitio de origen y se devolvió el último caché disponible.

---

# Fuente de datos

Las tasas son obtenidas automáticamente desde la sección **Mercado Informal de Divisas en Cuba** de elTOQUE. El scraper descarga la página completa y extrae la información de la tabla ubicada dentro de `id="mercado-informal"`. ([eltoque.com][1])

---

# Demo pública

Puedes probar el funcionamiento aquí:

**[https://cubaexchangeratescraper.free.nf/](https://cubaexchangeratescraper.free.nf/)**

---

# Licencia

Este proyecto se distribuye únicamente con fines educativos y de integración. El usuario es responsable de cumplir con los términos de uso y las condiciones del sitio del que se obtienen los datos.

[1]: https://eltoque.com/tasas-de-cambio-cuba/mercado-informal?utm_source=chatgpt.com "elTOQUE | Mercado Informal de Divisas en Cuba"
