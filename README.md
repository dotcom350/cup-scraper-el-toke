# Cuba Exchange Rate Scraper

Un scraper simple en PHP que obtiene automáticamente las tasas de cambio publicadas en **elTOQUE** y las expone como una API JSON.

## Características

* ✅ PHP puro (sin Composer).
* ✅ No requiere librerías externas.
* ✅ Cache automático de 1 hora.
* ✅ Devuelve JSON estructurado.
* ✅ Fácil de instalar en cualquier hosting con PHP.

---

# Requisitos

* PHP 7.4 o superior
* Extensión cURL habilitada
* Extensión DOM/XML habilitada

La mayoría de los hostings ya incluyen estas extensiones.

---

# Instalación

1. Copie los archivos al servidor.

```
index.php
```

2. Asegúrese de que PHP tenga permisos para crear el archivo:

```
cache.json
```

No es necesario crearlo manualmente. El sistema lo generará automáticamente la primera vez que se ejecute.

---

# Uso

Abra simplemente el archivo desde su navegador o mediante una petición HTTP.

Ejemplo:

```
https://tudominio.com/index.php
```

También puede consumirlo desde cualquier aplicación.

Ejemplo con JavaScript:

```javascript
fetch("https://tudominio.com/index.php")
    .then(r => r.json())
    .then(console.log);
```

Ejemplo con PHP:

```php
$data = json_decode(file_get_contents("https://tudominio.com/index.php"), true);

print_r($data);
```

Ejemplo con cURL:

```bash
curl https://tudominio.com/index.php
```

---

# Respuesta JSON

La respuesta tiene el siguiente formato:

```json
{
    "success": true,
    "source": "https://eltoque.com/tasas-de-cambio-cuba",
    "updated_at": "2026-07-20T22:00:00-04:00",
    "count": 7,
    "rates": [
        {
            "currency": "USD",
            "price": 670,
            "currency_to": "CUP",
            "change": null
        }
    ]
}
```

---

# Campos

| Campo      | Descripción                                        |
| ---------- | -------------------------------------------------- |
| success    | Indica si la operación fue exitosa.                |
| source     | URL desde donde se obtuvo la información.          |
| updated_at | Fecha y hora de la última actualización del caché. |
| count      | Cantidad de monedas encontradas.                   |
| rates      | Lista de tasas de cambio.                          |

Cada elemento de **rates** contiene:

| Campo       | Descripción                                   |
| ----------- | --------------------------------------------- |
| currency    | Moneda (USD, EUR, MLC, CAD, etc.).            |
| price       | Valor de la moneda en CUP.                    |
| currency_to | Moneda destino (CUP).                         |
| change      | Variación reportada por el sitio (si existe). |

---

# Caché

Para evitar hacer solicitudes innecesarias al sitio de origen, el scraper utiliza un sistema de caché.

* Duración del caché: **1 hora**
* Archivo utilizado:

```
cache.json
```

Durante ese período todas las solicitudes devolverán la misma información almacenada.

Al cumplirse una hora, el scraper descargará nuevamente los datos y actualizará automáticamente el caché.

---

# Forzar una actualización

Si desea actualizar inmediatamente los datos, simplemente elimine el archivo:

```
cache.json
```

La siguiente petición descargará nuevamente la información desde el sitio original.

---

# Notas

Este proyecto obtiene información pública disponible en:

https://eltoque.com/tasas-de-cambio-cuba

La estructura HTML del sitio podría cambiar en el futuro. Si eso ocurre, será necesario actualizar los selectores utilizados por el scraper.

---

# Licencia

Este proyecto se distribuye únicamente con fines educativos y de integración. El usuario es responsable de cumplir con los términos de uso del sitio web del que obtiene los datos.
