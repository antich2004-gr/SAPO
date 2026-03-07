# 📱 SAPO Widget & API REST

Sistema de widget embebible y API REST para mostrar la parrilla de programación en sitios web externos.

---

## 🌟 Características

- ✅ **Widget JavaScript sin iframe** - Se integra nativamente en tu sitio
- ✅ **API REST JSON** - Acceso completo a los datos de programación
- ✅ **CORS habilitado** - Funciona en cualquier dominio
- ✅ **Caché inteligente** - 5 minutos TTL (headers X-Cache)
- ✅ **Responsive** - Se adapta a móviles y tablets
- ✅ **Personalizable** - Usa tu configuración de colores y estilos
- ✅ **Lightweight** - Solo ~12KB

---

## 🚀 Inicio rápido

### **Opción 1: Widget JavaScript**

Añade este código donde quieras mostrar la parrilla:

```html
<div id="sapo-widget" data-station="TU_EMISORA"></div>
<script src="https://tudominio.com/sapo/sapo-widget.js"></script>
```

### **Opción 2: API REST**

```bash
curl https://tudominio.com/sapo/api_schedule.php?station=TU_EMISORA
```

---

## 📺 Widget JavaScript

### **Uso básico**

```html
<!DOCTYPE html>
<html>
<body>
    <div id="sapo-widget" data-station="mi_radio"></div>
    <script src="https://tudominio.com/sapo/sapo-widget.js"></script>
</body>
</html>
```

### **Múltiples widgets**

```html
<div class="sapo-widget" data-station="radio1"></div>
<div class="sapo-widget" data-station="radio2"></div>
<div class="sapo-widget" data-station="radio3"></div>

<script src="https://tudominio.com/sapo/sapo-widget.js"></script>
```

### **Configuración avanzada**

```html
<div id="sapo-widget"
     data-station="mi_radio"
     data-api-url="https://tudominio.com/sapo">
</div>
<script src="https://tudominio.com/sapo/sapo-widget.js"></script>
```

### **Atributos disponibles**

| Atributo | Descripción | Requerido | Default |
|----------|-------------|-----------|---------|
| `data-station` | Nombre de usuario de la emisora | ✅ Sí | - |
| `data-api-url` | URL base de la API | ❌ No | URL actual |

### **Personalización**

Los colores y estilos se configuran desde el panel de SAPO:

- **Color principal** - Color de headers y acentos
- **Color de fondo** - Fondo del widget
- **Estilo** - Moderno, clásico, compacto, minimalista
- **Tamaño de fuente** - Pequeño, mediano, grande

El widget aplicará automáticamente esta configuración.

### **Ejemplo en vivo**

Abre el archivo `widget_example.html` en tu navegador para ver un ejemplo funcional.

---

## 🔌 API REST

### **Endpoint**

```
GET /api_schedule.php?station={username}
```

### **Parámetros**

| Parámetro | Tipo | Descripción | Requerido |
|-----------|------|-------------|-----------|
| `station` | string | Nombre de usuario de la emisora | ✅ Sí |

### **Ejemplo de petición**

```bash
curl -X GET "https://tudominio.com/sapo/api_schedule.php?station=mi_radio"
```

### **Ejemplo de respuesta**

```json
{
  "success": true,
  "station": {
    "username": "mi_radio",
    "name": "Mi Radio Comunitaria",
    "stream_url": "https://stream.ejemplo.com/radio.mp3"
  },
  "config": {
    "color": "#10b981",
    "background_color": "#ffffff",
    "style": "modern",
    "font_size": "medium"
  },
  "schedule": [
    {
      "name": "Lunes",
      "programs": [
        {
          "title": "Noticias Matinales",
          "description": "Las noticias más importantes del día",
          "image": "https://ejemplo.com/imagen.jpg",
          "type": "live",
          "start_time": "08:00",
          "end_time": "09:00",
          "url": "https://ejemplo.com/noticias",
          "social": {
            "twitter": "@noticiastv",
            "instagram": "noticiastv",
            "facebook": "noticiastv"
          }
        }
      ]
    }
  ],
  "generated_at": "2024-03-07T10:30:00+01:00",
  "powered_by": "SAPO"
}
```

### **Estructura de respuesta**

#### **Objeto principal**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `success` | boolean | Indica si la petición fue exitosa |
| `station` | object | Información de la emisora |
| `config` | object | Configuración de estilos |
| `schedule` | array | Programación por días |
| `generated_at` | string | Fecha/hora de generación (ISO 8601) |
| `powered_by` | string | Siempre "SAPO" |

#### **Objeto station**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `username` | string | Nombre de usuario |
| `name` | string | Nombre completo de la emisora |
| `stream_url` | string | URL del stream (opcional) |

#### **Objeto config**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `color` | string | Color principal (hex) |
| `background_color` | string | Color de fondo (hex) |
| `style` | string | Estilo (modern/classic/compact/minimal) |
| `font_size` | string | Tamaño fuente (small/medium/large) |

#### **Array schedule**
Array de 7 elementos (uno por día de la semana):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Nombre del día |
| `programs` | array | Lista de programas del día |

#### **Objeto program**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `title` | string | Título del programa |
| `description` | string | Descripción |
| `image` | string | URL de imagen |
| `type` | string | Tipo: `live`, `program`, `music` |
| `start_time` | string | Hora inicio (HH:mm) |
| `end_time` | string | Hora fin (HH:mm) |
| `url` | string | URL del programa |
| `social` | object | Redes sociales |

### **Códigos de estado HTTP**

| Código | Significado |
|--------|-------------|
| `200` | ✅ Éxito |
| `400` | ❌ Parámetro station inválido o faltante |
| `404` | ❌ Emisora no encontrada |
| `500` | ❌ Error interno del servidor |

### **Headers de respuesta**

```
Content-Type: application/json; charset=utf-8
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET
X-Cache: HIT|MISS
Cache-Control: public, max-age=300
Expires: Thu, 07 Mar 2024 10:35:00 GMT
```

---

## ⚡ Caché y rendimiento

### **Sistema de caché**

La API usa un sistema de caché de dos niveles:

1. **Caché del servidor** (5 minutos)
   - Archivo: `cache/api_schedule_{username}.cache`
   - TTL: 300 segundos
   - Header: `X-Cache: HIT` (desde caché) o `MISS` (generado)

2. **Caché del navegador** (5 minutos)
   - Header: `Cache-Control: public, max-age=300`
   - Header: `Expires` con fecha de expiración

### **Invalidación de caché**

El caché se invalida automáticamente cuando:
- Se crea/modifica/elimina un programa
- Se actualiza la configuración de la emisora

Para invalidar manualmente:

```php
<?php
require_once 'includes/cache.php';
cacheInvalidate('api_schedule_mi_radio');
```

### **Monitoreo de caché**

Ver hit rate:

```bash
# Ver todas las respuestas
tail -f /var/log/apache2/access.log | grep api_schedule

# Solo cache HITs
tail -f /var/log/apache2/access.log | grep "X-Cache: HIT"

# Solo cache MISSes
tail -f /var/log/apache2/access.log | grep "X-Cache: MISS"
```

Verificar headers:

```bash
curl -I "https://tudominio.com/sapo/api_schedule.php?station=mi_radio"
```

---

## 🔒 Seguridad y CORS

### **CORS habilitado**

La API incluye headers CORS para permitir peticiones desde cualquier dominio:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET
Access-Control-Allow-Headers: Content-Type
```

### **Validación de entrada**

- El parámetro `station` se valida con `validateInput()`
- Solo se permiten caracteres alfanuméricos, guiones y guiones bajos
- Longitud máxima: 50 caracteres

### **Rate limiting**

No hay rate limiting en la API REST (solo caché HTTP).

Para añadir rate limiting personalizado, edita `api_schedule.php`.

---

## 💻 Ejemplos de uso

### **JavaScript (Fetch)**

```javascript
async function getSchedule(station) {
    const response = await fetch(
        `https://tudominio.com/sapo/api_schedule.php?station=${station}`
    );

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    return data;
}

// Uso
getSchedule('mi_radio')
    .then(data => {
        console.log(data.station.name);
        console.log(data.schedule);
    })
    .catch(error => {
        console.error('Error:', error);
    });
```

### **jQuery**

```javascript
$.getJSON('https://tudominio.com/sapo/api_schedule.php?station=mi_radio')
    .done(function(data) {
        console.log(data.station.name);
        console.log(data.schedule);
    })
    .fail(function() {
        console.error('Error al cargar programación');
    });
```

### **PHP (cURL)**

```php
<?php
$station = 'mi_radio';
$url = "https://tudominio.com/sapo/api_schedule.php?station=" . urlencode($station);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "Emisora: " . $data['station']['name'] . "\n";
    echo "Programas: " . count($data['schedule'][0]['programs']) . "\n";
} else {
    echo "Error: HTTP $httpCode\n";
}
```

### **Python (requests)**

```python
import requests

station = 'mi_radio'
url = f'https://tudominio.com/sapo/api_schedule.php?station={station}'

response = requests.get(url)

if response.status_code == 200:
    data = response.json()
    print(f"Emisora: {data['station']['name']}")
    print(f"Días: {len(data['schedule'])}")
else:
    print(f"Error: HTTP {response.status_code}")
```

---

## 🐛 Troubleshooting

### Error: "Emisora no encontrada"

**Causa:** El nombre de usuario no existe en SAPO.

**Solución:** Verifica que el parámetro `station` sea correcto.

### Error: CORS bloqueado

**Causa:** Navegador antiguo o configuración incorrecta.

**Solución:** La API ya incluye headers CORS. Verifica que tu servidor no los esté sobrescribiendo.

### Widget no se muestra

**Causa:** Script no cargado o atributo `data-station` faltante.

**Solución:**
1. Abre la consola del navegador (F12)
2. Verifica que `sapo-widget.js` se haya cargado
3. Asegúrate de que el contenedor tenga `data-station="..."`

### Programación desactualizada

**Causa:** Caché activo.

**Solución:**
1. Espera 5 minutos (TTL de caché)
2. O invalida el caché manualmente:
   ```bash
   rm -f cache/api_schedule_*.cache
   ```

### Respuesta lenta

**Causa:** Cache MISS (primera carga o caché expirado).

**Solución:** Es normal. Las siguientes peticiones serán instantáneas (cache HIT).

---

## 📚 Archivos relacionados

```
SAPO/
├── api_schedule.php        # API REST
├── sapo-widget.js          # Widget JavaScript
├── widget_example.html     # Ejemplo de uso
├── includes/
│   └── cache.php           # Sistema de caché
└── WIDGET.md               # Esta documentación
```

---

## 🎯 Migrado desde GRILLO

Este widget y API fueron portados desde [GRILLO](https://github.com/antich2004-gr/grillo) y adaptados a SAPO.

**Diferencias:**
- GRILLO: Base de datos MySQL
- SAPO: Archivos JSON
- Misma API REST y widget
- Mismo formato de respuesta JSON

---

## 📞 Soporte

Para problemas o preguntas:

1. Revisa esta documentación
2. Abre `widget_example.html` para ver un ejemplo funcional
3. Verifica los logs del servidor
4. Reporta issues en GitHub

---

**Desarrollado con ❤️ para radios comunitarias**
