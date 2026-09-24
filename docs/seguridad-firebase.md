# Seguridad de la numeración (Firebase Realtime Database)

El correlativo de proformas vive en el nodo `contador_pdf` del Realtime Database
`proforma-ready-default-rtdb`. La aplicación lo consume **solo** a través de
`App\Services\ContadorProformaService` (nunca desde las vistas ni con cURL suelto).

## Estado original (riesgo H-16)
El nodo estaba **abierto**: cualquiera con la URL podía leerlo y sobrescribirlo
(p. ej. `PUT {"contador_pdf":1}`), lo que provocaría folios duplicados masivos.

## 1. Autenticación desde la aplicación (YA IMPLEMENTADO en el código)
El servicio añade el parámetro `auth` a cada petición cuando existe la variable
`FIREBASE_SECRET`:

```
https://<proyecto>-default-rtdb.firebaseio.com/contador_pdf.json?auth=<SECRETO>
```

- `FIREBASE_SECRET` vacío → peticiones anónimas (comportamiento histórico, sin romper nada).
- `FIREBASE_SECRET` definido → peticiones autenticadas.
- El secreto se obtiene en la consola de Firebase:
  **Configuración del proyecto → Cuentas de servicio → Secretos de la base de datos**.
- En producción se define como **variable de entorno del servicio en Railway**;
  NUNCA se escribe en `.env.example` ni en el repositorio.

## 2. Reglas del RTDB (paso manual obligatorio en la consola de Firebase)
Una vez definido `FIREBASE_SECRET` en Railway y redesplegado el servicio, pegar en
**Realtime Database → Reglas**:

```json
{
  "rules": {
    ".read": false,
    ".write": false
  }
}
```

## 3. Checklist de verificación antes y después de cerrar el nodo
1. `GET .../contador_pdf.json` **sin** `?auth` → debe responder `401 Permission denied`.
2. La aplicación (con el secreto) sigue leyendo y guardando proformas.
3. `GET /` muestra el correlativo real (no el provisional `0001`).
4. Se crea una proforma de prueba y el folio avanza exactamente en 1.

```powershell
# Anónimo (debe fallar cuando las reglas estén aplicadas)
Invoke-WebRequest "https://proforma-ready-default-rtdb.firebaseio.com/contador_pdf.json"

# Autenticado (debe responder el JSON del contador)
Invoke-WebRequest "https://proforma-ready-default-rtdb.firebaseio.com/contador_pdf.json?auth=$env:FIREBASE_SECRET"
```

## 4. Robustez adicional del contador (H-01 / H-04 / H-05)
- **Escritura condicional (ETag / `If-Match`)**: `incrementarContador()` reenvía el ETag
  de la última lectura; si otro proceso cambió el contador, Firebase responde `412` y el
  servicio resuelve el conflicto (relee y solo reescribe si el contador quedó por detrás),
  hasta 3 intentos. Así la reserva del folio no duplica valores.
- **Índice `UNIQUE` en `proformas.codigo_proforma`** (migración
  `2026_09_23_000000_deduplicar_codigo_proforma_and_unique_table`): la base de datos es
  el árbitro final; un folio repetido es imposible.
- **Orden de operaciones (H-04)**: el contador se incrementa **después** del `commit()`;
  si el guardado falla y hay rollback, el folio **no** queda quemado.
- **PRG (H-05)**: tras crear/editar se responde `302` hacia `GET /proformas/{id}/pdf`,
  de modo que un F5 repite el GET y nunca reenvía el formulario.
