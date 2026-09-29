# PLAN.md — App de Jueces: Orden de Clasificados (Torneo Macro Regional 2026)

Torneo estudiantil (Arequipa, Moquegua, Tacna). Los jueces ordenan a los clasificados por
**Disciplina > Categoría > Clase** con autoguardado. Sin calificación, solo orden + observación opcional.
Plazo: puesta en producción en ~2 horas. Sin pruebas automatizadas.
En docs/ crea un log simple de desarrollo de la aplicación en txt e inserta nuevas filas mientras desarrollas.
Para el log usa el formato: Fecha y Hora → Acción → Estado (Iniciado, Revisión, Terminado, Fallido, puedes elegir otros).
No actualices lineas en el log, solo agregas una nueva línea.
Realiza commits despues de cada paso importante o cuando hagas correcciones.

---

## 1. Stack

| Elemento | Elección | Motivo |
|---|---|---|
| Backend | Laravel 11 (PHP 8.2) | Rápido de montar, disponible en el equipo |
| BD | **MySQL** (XAMPP) | Ya instalado. *Nota: `mysql` no está en PATH → usar `C:\xampp\mysql\bin\mysql`* |
| Frontend | Blade + **jQuery 3 + jQuery UI Sortable + jquery-ui-touch-punch** (CDN) | Sin npm, sin build, drag & drop táctil |
| Estilos | Usa Beer CSS, mobile-first | Minimalista |
| Auth | Manual (`Auth::attempt`) + middleware `rol` | Evita Breeze/Jetstream |
| Importación | Comando artisan que lee CSV **por encabezados** | Funciona con las 28 col de DATA GRAL.MACRO y las 31 de GRAL-METRICAS |

**Directorio existente:** Directorio ya contiene laravel instalado y composer instalado.
**Base de datos existente:** `mysql -u root -p` (`root` por defecto) → Base de datos `torneo` creada.
**Empates permitidos por defecto:** varios participantes pueden compartir puesto (ver §5).

---

## 2. Base de datos (4 tablas, sin normalizar)

```sql
-- ÚNICA tabla de clasificados, plana, una fila por participante
CREATE TABLE participantes (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- columnas originales del CSV
  id_anio             INT            NULL,
  id_ficha_evento     BIGINT         NULL,
  dre                 VARCHAR(100)   NULL,
  ugel                VARCHAR(100)   NULL,
  regionie            VARCHAR(60)    NULL,
  provinciaie         VARCHAR(80)    NULL,
  distritoie          VARCHAR(80)    NULL,
  cod_mod             VARCHAR(20)    NULL,
  cod_mod_origen      VARCHAR(20)    NULL,
  ie                  VARCHAR(150)   NULL,
  nivel_educativo     VARCHAR(40)    NULL,
  grado               VARCHAR(30)    NULL,
  categoria           VARCHAR(50)    NULL,   -- CATEGORÍA A/B/C...
  disciplina          VARCHAR(80)    NULL,   -- VOLEY PLAYA, ATLETISMO, AJEDREZ...
  clase_variante      VARCHAR(100)   NULL,   -- CLASE_VARIANTE_DE_DISCIPLINA
  genero              VARCHAR(20)    NULL,   -- VARONES / DAMAS
  nombre_etapa        VARCHAR(80)    NULL,
  tipo_participante   VARCHAR(20)    NULL,   -- TITULAR / SUSTITUTO
  tipo_documento      VARCHAR(20)    NULL,
  numdoc_participante VARCHAR(20)    NULL,
  apepat              VARCHAR(60)    NULL,
  apemat              VARCHAR(60)    NULL,
  nombres             VARCHAR(80)    NULL,
  fecha_nacimiento    DATE           NULL,
  telefono            VARCHAR(20)    NULL,
  fecha_registro      DATETIME       NULL,
  fecha_actualizacion DATETIME       NULL,
  estado_ficha        VARCHAR(30)    NULL,
  -- 3 columnas extra (atletismo, natación, paratletismo, taekwondo); NULL en el resto
  dsc_prueba          VARCHAR(100)   NULL,
  metrica             VARCHAR(50)    NULL,
  marca               VARCHAR(30)    NULL,
  -- resultado del juez (autoguardado)
  puesto              INT            NULL,   -- NULL = sin asignar; duplicados = EMPATE
  KEY idx_grupo (disciplina, categoria, clase_variante, dsc_prueba),
  KEY idx_doc (numdoc_participante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- observación opcional: 1 fila por grupo (upsert)
CREATE TABLE observaciones (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  disciplina VARCHAR(80)  NOT NULL,
  categoria  VARCHAR(50)  NOT NULL,
  genero     VARCHAR(20)  NOT NULL DEFAULT '',   -- F13: DAMAS / VARONES (parte de la clave)
  clase      VARCHAR(100) NOT NULL,
  prueba     VARCHAR(100) NOT NULL DEFAULT '',   -- '' cuando no aplica
  texto      TEXT         NULL,
  user_id    BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NULL, created_at TIMESTAMP NULL,
  UNIQUE KEY uq_grupo (disciplina, categoria, genero, clase, prueba)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- auditoría simple: quién modificó el puesto y cuándo (1 fila por cambio real)
CREATE TABLE puesto_auditoria (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participante_id  BIGINT UNSIGNED NOT NULL,
  puesto_anterior  INT            NULL,   -- NULL = sin asignar
  puesto_nuevo     INT            NULL,   -- NULL = limpiado
  user_id          BIGINT UNSIGNED NULL,
  user_nombre      VARCHAR(120)   NULL,   -- copia del nombre del juez al momento de editar
  created_at       TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_part (participante_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- users estándar de Laravel + rol
ALTER TABLE users ADD COLUMN rol ENUM('admin','juez') NOT NULL DEFAULT 'juez';
```

> El `puesto` vive en la misma tabla `participantes` → el autoguardado es un `UPDATE` por fila en transacción.

---

## 3. Estructura de archivos

```
app/
├── Http/Controllers/
│   ├── AuthController.php          login/logout
│   ├── JuezController.php          pantalla de lista (GET /juez)
│   ├── GrupoController.php         API: grupos, participantes, guardar-orden, observación, historial
│   └── ImportController.php        subida CSV vía web (admin, opcional)
├── Http/Middleware/RolMiddleware.php
├── Models/Participante.php         $guarded = [], sin casts
├── Models/Observacion.php
├── Models/PuestoAuditoria.php      solo lectura (insert lo hace GrupoController)
└── Console/Commands/
    ├── ImportarCsv.php             torneo:importar {ruta} {--fresh}
    └── CrearJuez.php               juez:crear {email} {nombre} {password?}
resources/views/
├── layouts/app.blade.php           header sticky, toast, CDN jQuery
├── auth/login.blade.php
├── juez/index.blade.php            selects encadenados + lista + drag&drop
└── admin/importar.blade.php        (opcional)
public/css/app.css
public/js/juez.js                   lógica de UI, debounce y autosave
database/migrations/xxxx_create_participantes_table.php  (+ observaciones, + alter users.rol)
```

---

## 4. Rutas

```
GET  /login                AuthController@mostrarLogin
POST /login                AuthController@login      (Auth::attempt + redirect por rol)
POST /logout               AuthController@logout

GET  /                     redirect → /juez  (o /admin si rol=admin)

-- juez (middleware auth)
GET  /juez                 JuezController@index
GET  /api/grupos           GrupoController@grupos     → filas planas con conteos (F13)
GET  /api/participantes    GrupoController@lista      → ?categoria=&disciplina=&genero=&clase=&prueba=
POST /api/guardar-orden    GrupoController@guardar    → {items:[{id,puesto}], grupo:{...}}
POST /api/guardar-observacion GrupoController@observacion → {grupo:{...}, texto}
GET  /api/historial/{participante} GrupoController@historial → [{created_at, user_nombre, puesto_anterior, puesto_nuevo}] (últimas 20)

-- admin (middleware auth + rol=admin)
GET  /admin/resultados     JuezController@resultados → misma UI del juez en SOLO LECTURA (§13 F2)
POST /admin/importar       ImportController@importar

-- export (auth: juez y admin)
GET  /resultados/pdf       GrupoController@pdf → vista imprimible del grupo filtrado (§13 F3)
```

---

## 5. Reglas de negocio (empates incluidos)

1. **Clave de grupo:** `categoria | disciplina | genero | clase_variante | dsc_prueba` (`dsc_prueba` = `''` si es NULL). *(F13: el género entra en la clave → DAMAS y VARONES se listan y se guardan por separado.)*
   *Supuesto:* si una disciplina con métrica tiene `dsc_prueba`, ese valor también segmenta la lista.
2. **Puestos con empates permitidos (por defecto):**
   - Varios participantes pueden tener el mismo `puesto` (1° empatado, 2° empatado...).
   - **No hay validación de unicidad** ni lógica de intercambio (más simple que la versión sin empates).
   - Asignar un puesto desde el dropdown = escribirlo tal cual.
   - Display: si 2+ comparten puesto → badge `1° (empate)`; orden `ORDER BY puesto, id`.
   - `puesto NULL` = sin asignar, siempre al final de la lista.
3. **Autoguardado:** cualquier cambio (dropdown, drag, limpiar) → recalcular puestos locales →
   debounce **600 ms** → `POST /api/guardar-orden` → servidor hace `UPDATE` en **transacción**
   (1 UPDATE por id) → toast `Guardado ✓`. Flag `guardando` anti-reentrada; si llega un cambio
   mientras se guarda, se reprograma.
4. **Concurrencia:** última escritura gana (uso único del sistema, aceptable).
5. **Observación:** textarea por grupo, mismo debounce, upsert en `observaciones`.
6. **Auditoría de puesto:** dentro del `DB::transaction` de guardar-orden, por cada fila cuyo `puesto`
   realmente cambió → `INSERT` en `puesto_auditoria` (anterior, nuevo, user_id, user_nombre).
   Si el puesto no cambió, no se inserta fila (evita ruido).

---

## 6. API — payload de guardar-orden

```json
POST /api/guardar-orden
{
  "grupo": {"disciplina":"ATLETISMO","categoria":"CATEGORÍA B","clase":"VARONES","prueba":"800 metros planos"},
  "items": [ {"id": 101, "puesto": 1}, {"id": 102, "puesto": 1}, {"id": 103, "puesto": 2} ]
}
→ 200 {"ok":true}
```
Servidor: `DB::transaction(...)` → UPDATE por fila (puesto distinto por id) + `limpiar: true`
pone en `NULL` los del grupo que no vengan en `items` (usado por el botón "Limpiar orden",
que envía `items: []`). Dentro de la misma transacción se registra la auditoría (§5.6).

---

## 7. UI/UX (mobile-first, minimalista)

- **Header sticky:** nombre del torneo, usuario, logout. Breadcrumb `Disciplina / Categoría / Clase / Prueba`.
- **Navegación:** 5 `<select>` encadenados (Categoría → Disciplina → Género → Clase → Prueba si aplica;
  F13) — con input de búsqueda si un dropdown tiene más de 5 opciones; al cambiar uno se recarga vía AJAX.
  Badge con nº de clasificados.
- **Fila (card):** badge de puesto (grande, izquierda) · nombre completo · IE / UGEL / Región ·
  `<select>` de puesto (1..N) · handle de arrastre (☰). Mín. 56 px alto, touch ≥44 px.
- **Drag & drop:** jQuery UI Sortable + touch-punch, `delay: 200, touchStartThreshold: 5` para no
  robar el scroll; fallback con botones ↑/↓ por fila (accesibilidad / si el drag falla).
- **Indicador de guardado:** `Guardando…` / `Guardado ✓` en el header + toast autohide 1.5 s.
- **Buscador** por nombre o IE (filtro local en JS).
- **Observación:** textarea colapsada `Observación del juez (opcional)`, mismo debounce.
- **Botón** `Limpiar orden` (puestos → NULL) con `confirm()` del navegador.
- **Historial de puesto:** botón pequeño por fila (icono 🕘, `aria-label="Historial"`, ≥44 px) que
  abre un `modal` con tabla `Fecha/hora · Juez · Puesto anterior → nuevo`; datos vía
  `GET /api/historial/{id}` al abrir. Solo lectura.
- **Colores:** neutros + acento índigo; empatados en ámbar.

---

## 8. Importación CSV

```bash
php artisan torneo:importar "C:\xampp\htdocs\torneo\DATA GRAL.MACRO.csv" --fresh
php artisan torneo:importar "C:\xampp\htdocs\torneo\GRAL-METRICAS.csv"     # añade pruebas/marcas
```
- Lee la **primera fila como encabezados** y mapea por nombre → acepta 28 o 31 columnas indistintamente
  (columna ausente en el CSV → NULL en la BD).
- **Encoding:** los CSV traen CP1252/ISO-8859-1 (`CATEGOR'A`, `SE�ORA`) → convertir a UTF-8
  (`mb_convert_encoding($fila, 'UTF-8', 'Windows-1252')`) antes de insertar.
- Fechas `d/m/Y` → `DATE`; `d/m/Y H:i` → `DATETIME` (`DateTime::createFromFormat`).
- Insert en chunks de 500 con `insert()`; `--fresh` = `TRUNCATE` antes (importación idempotente).
- Sustituto/Titular y `ESTADO_FICHA` se importan tal cual (el juez no filtra).

---

## 9. Usuarios juez

**Fix F4 (29/09/2026): login por `usuario` (sin email).** Seeder oficial:

```bash
php artisan db:seed --class=UsuariosSeeder
```

Crea (idempotente, `updateOrCreate` por `usuario`):

| usuario | nombre | rol | contraseña |
|---|---|---|---|
| `admin` | Admin | admin | alfanumérica aleatoria de 8 caracteres (se imprime en consola) |
| `juez1` | Juez 1 | juez | idem |
| `juez2` | Juez 2 | juez | idem |

Alternativa manual: `php artisan torneo:crear {usuario} {nombre} {password?} {rol?}` (p. ej. `php artisan torneo:crear juez3 "Juez Tres" secret juez`).
Acceso: **todos los jueces ven el árbol completo** disciplina > categoría > clase (decisión validada).
Solo el admin ve `/admin/importar` y `/admin/usuarios` (Fix F5).

---

## 10. Cronograma de implementación (~2 h)

| # | Tarea | Min |
|---|---|---|
| 1 | `composer create-project laravel/laravel .`, `.env` MySQL, `php artisan migrate` | 15 |
| 2 | Migración `participantes` + `observaciones` + `puesto_auditoria` + `users.rol`; modelos | 10 |
| 3 | Auth (login/logout) + middleware rol + layout/login blade | 20 |
| 4 | Comando `torneo:importar` + ejecutar con los CSV reales | 20 |
| 5 | `GrupoController` (grupos, lista, guardar-orden + auditoría, observación, historial) | 35 |
| 6 | UI: `juez/index.blade.php` + `app.css` + `juez.js` (autosave/sortable/empates/botón historial) | 45 |
| 7 | Comando `juez:crear` + usuarios de prueba | 5 |
| 8 | Verificación en móvil + laptop, ajustes finales | 10 |

**Total ≈ 160 min.** Si hay retraso: eliminar `ImportController` web (ya cubierto por artisan)
y el buscador local.

---

## 11. Puesta en producción

1. `composer install --no-dev -o`
2. `.env` (BD, `APP_URL`), `php artisan key:generate`
3. `php artisan migrate --force`
4. Importar CSVs (§8) y crear usuarios (§9)
5. Document root → `public/`; permisos en `storage/` y `bootstrap/cache/`
6. HTTPS recomendado (mejor experiencia táctil y futuras APIs en móvil)

---

## 12. Riesgos y decisiones asumidas

| Riesgo | Mitención |
|---|---|
| Dos jueces editan la misma clase a la vez | Última escritura gana (uso único, aceptable) |
| CSV en Latin-1/CP1252 con comillas | `fgetcsv` + conversión de encoding + BOM |
| MySQL fuera de PATH en Windows | Ruta `C:\xampp\mysql\bin\` o agregar al PATH |
| Drag & drop roba el scroll en móvil | `delay: 200` + touch-punch + fallback ↑/↓ |
| Sin pruebas automatizadas | Checklist manual al final de §10 |
| Empates (decisión) | **Permitidos** — simplifica endpoint y UI (§5.2) |

---

---

## 13. Fixes posteriores a la validación (29/09/2026)

| # | Fix | Detalle técnico |
|---|---|---|
| **F1** | Dropdown **Prueba** atascado (p. ej. ATLETISMO) | Unificar el refresco de `#sel-prueba` en una sola función (`refrescarPrueba()`) invocada al cambiar disciplina, categoría o clase: vacía el select, quita el valor seleccionado, elimina duplicados y la rellena **solo** con las pruebas de la disciplina + clase elegidas (se oculta si no hay). `GrupoController@grupos` devuelve `pruebas` sin duplicados (`array_unique`) y el layout carga `juez.js`/`app.css` con `?v=filemtime` (cache-busting para evitar JS viejo en caché). |
| **F2** | Vista admin de resultados (solo lectura) | `GET /admin/resultados` (rol=admin) reutiliza `juez/index.blade.php` con flag `soloLectura`: sin drag & drop, sin select de puesto, sin ↑/↓, sin "Limpiar orden" y observación en solo lectura; conserva buscador, historial y export PDF. El root `/` del admin redirige aquí (importar sigue en `/admin/importar`, con enlace en el header). |
| **F3** | Exportar **PDF** (preview web) | Botón `Exportar PDF` en la barra (juez y admin) → `GET /resultados/pdf?disciplina=&categoria=&clase=&prueba=` → vista imprimible con el grupo filtrado (puestos, empates, IE/UGEL/Región, métricas, observación y firmas) + botón "Imprimir / Guardar PDF" (`window.print()`). |
| **F4** | **Seeder de usuarios** + login por `usuario` | `database/seeders/UsuariosSeeder.php`: admin (`Admin`), `juez1` (`Juez 1`), `juez2` (`Juez 2`) con contraseña alfanumérica aleatoria de 8 caracteres (impresa en consola), idempotente vía `updateOrCreate`. Nueva columna `users.usuario` (única, backfill desde el email); login pasa de email → usuario (`AuthController` + `auth/login.blade.php`); `torneo:crear` adaptado a `{usuario}`. Ver §9. |
| **F5** | **Gestión de usuarios** desde el admin | `UsuarioController` + rutas bajo `/admin/usuarios` (rol=admin): `GET` listado + formularios, `POST /crear` (usuario, nombre, contraseña, rol) y `POST /{id}/actualizar` (nombre y/o contraseña; contraseña vacía = no cambia). Vista `admin/usuarios.blade.php` (Beer CSS, tarjetas) + enlace "Usuarios" en el header admin. |
| **F6** | **Listado legible en pantalla pequeña** | En `public/css/app.css`, media query `≤699px` sobre `.fila`: `flex-wrap` con `order` → badge de puesto + acciones (select de puesto, historial, arrastre) quedan en la **primera línea** y `.fila-info` (nombre, IE/UGEL/Región, pruebas) pasa a una **segunda línea a ancho completo**, sin `ellipsis` (texto envuelve). En `≥700px` no cambia nada. |
| **F7** | **DNI antes del nombre + búsqueda por DNI** | `filaHtml()` (juez.js) pinta el `numdoc_participante` antes del nombre (span `.fila-dni`) y lo añade al `data-buscar`; el buscador local filtra por **DNI, nombre o IE**. El filtro solo oculta filas: el juez sigue asignando puestos (select/arrastrado) con la búsqueda activa. `GrupoController@buscarGrupo` añade `nivel_educativo` al `select` (usa F9). |
| **F8** | **Buscador en su propia línea al inicio de la lista** | El input sale de `.barra` (acciones) y pasa al bloque `.busqueda`, colocado **justo antes de `<ul id="lista">`**, en línea propia y **siempre visible también en pantallas pequeñas** (sin media query que lo oculte). Placeholder `Buscar por DNI o nombre…` y botón ✕ dentro del input (`.buscador-x`, se muestra solo si hay texto) para limpiar la búsqueda; se oculta el ✕ nativo de `input[type=search]`. |
| **F9** | **Nivel (Primaria/Secundaria) y grado del clasificado** | Debajo de la línea IE · UGEL · Región se añade otra línea `.fila-nivel` con `nivel_educativo` y `grado` (p. ej. `Secundaria · SEGUNDO`), usando `nivel_educativo` (F7) y `grado` ya seleccionados. |
| **F10** | **Observación al final de la vista** | El `<details class="observacion" id="panel-observacion">` se mueve **después** de `<ul id="lista">` y de `#vacio` (antes estaba entre la barra y la lista); sin cambios de lógica (mismo debounce y upsert). |
| **F11** | **Autoselección de categoría/clase única** | Al elegir la disciplina, si esta tiene **una sola categoría** se selecciona automáticamente en `#sel-categoria`; y si la categoría elegida tiene **una sola clase**, se selecciona automáticamente en `#sel-clase`. Si el grupo queda completo se carga el listado de participantes sin clics adicionales. Nuevo helper `refrescarClase()` (equivalente a `refrescarPrueba()`) centraliza el llenado de `#sel-clase` y la auto-selección; `alCambiarDisciplina()` y `alCambiarCategoria()` lo invocan. Los dropdowns siguen visibles con la opción única elegida. |
| **F12** | **Dropdown de puesto con filtro al tipear (>5 puestos)** | Cuando el grupo tiene **más de 5 participantes**, el `<select class="sel-puesto">` se reemplaza por un combo (`comboPuesto()`: botón + panel con `<input class="combo-filtro">` y `<ul class="combo-lista">`): al tipear se filtran las opciones por número (subcadena: `1` → 1°, 10°, 11°…), **Enter** elige la primera visible, **Esc** o clic fuera cierran el panel y `Sin coincidencias` si nada coincide. Con ≤5 puestos se mantiene el `<select>` nativo. Estilos en `app.css` (`.combo-puesto`, `.combo-panel`, `.combo-filtro`, `.combo-lista`). |
| **F13** | **Orden de filtros: Categoría → Disciplina → Género → Clase → Prueba + buscador en los dropdowns** | Los filtros se reordenan y reencadenan en ese orden (antes la Disciplina era la raíz y **no había filtro de género**: DAMAS y VARONES salían mezclados). `GrupoController@grupos` devuelve ahora **filas planas** `{categoria, disciplina, genero, clase, prueba, total}` y `juez.js` arma el cascada con `CAMPOS = ['categoria','disciplina','genero','clase','prueba']` (auto-selección de la opción única; `seleccionCompleta()` exige categoría + disciplina + género + clase). **`genero` entra en la clave de grupo**: `lista`, `guardar-orden`, `guardar-observacion` y `pdf` filtran por `grupo.genero`, y la tabla `observaciones` gana la columna `genero` (migración `add_genero_to_observaciones`; `uq_grupo` e `idx_grupo` actualizados). Cualquier dropdown con **más de 5 opciones** se pinta como combo con `<input>` de búsqueda (misma mecánica que F12: filtra al tipear, **Enter** elige, **Esc**/clic fuera cierran, `Sin coincidencias`); con ≤5 opciones se mantiene el `<select>` nativo. |
| **F14** | **Dropdown Clase oculto si hay una sola clase** | Si, tras aplicar los filtros previos, la disciplina solo tiene **1 clase**, `renderFiltro('clase')` la auto-selecciona y oculta `#campo-clase` (el resto del cascada —prueba— sigue cargando); con 2 o más clases el dropdown se muestra normalmente. |
| **F15** | **Conteo de clasificados en cada opción de Prueba** | Cada opción del dropdown `#filtro-prueba` muestra `Nombre (N)` con los clasificados de esa prueba dentro del grupo filtrado (incluida `Todas (N)`, donde N = filas sin `dsc_prueba`); el conteo proviene del campo `total` de las filas planas de `/api/grupos`. Si la disciplina/clase no tiene pruebas, el dropdown se oculta. |
| **F16** | **Altura del combo Disciplina/Prueba igual a los selects** | El `<button class="combo-btn">` medía **62 px** frente a los 44 px de los `<select>` nativos: Beer CSS aplica `button { box-sizing: content-box; display: inline-flex }` y la regla global `* { box-sizing: border-box }` de `app.css` tiene especificidad 0 → no gana sobre Beer. Fix en `public/css/app.css`: a `.campo .combo-campo .combo-btn` se le añade `box-sizing: border-box; display: block;` → **44 px exactos**, idénticos a `.campo select` (el `display: block` elimina además la línea base inline que agrandaba `.combo-campo`). |
| **F17** | **Valor visible y panel cerrado al elegir opción en el combo** | Dos causas: (a) `alCambiarFiltro()` solo repintaba los filtros **posteriores**, por lo que la etiqueta del botón quedaba en `Seleccione…`/`Todas` aunque el filtro sí se aplicaba → `renderFiltro(campo, valor)` acepta ahora un valor preseleccionado y `alCambiarFiltro()` repinta el control actual con la opción elegida (`Nombre (N)`, clase `.activa` y panel `hidden`); (b) los controles viven dentro de un `<label class="campo">` y el navegador reenvía el click al primer control labelable (`combo-btn`) **después** del dispatch, lo que reabría el panel recién cerrado → el handler del `li` llama ahora a `e.preventDefault()` (cancela el reenvío del label) y `cerrarCombos()` restablece `aria-expanded` también en los combos de filtro. |
| **F18** | **Rutas de las vistas con helpers `route()` / `asset()` / `url()`** | Todas las URL de `resources/views` se generan con helpers en vez de texto estático: (a) se nombran en `routes/web.php` las rutas que faltaban —`GET /admin/importar` → `admin.importar`, `POST /admin/usuarios/crear` → `admin.usuarios.crear`, `POST /admin/usuarios/{id}/actualizar` → `admin.usuarios.actualizar`—; (b) el header admin (`layouts/app.blade.php`) pasa de `url('/admin/…')` literal a `route('admin.resultados')`, `route('admin.importar')` y `route('admin.usuarios')`, y el estado activo de `request()->is('admin/…')` (ruta escrita a mano) a `request()->routeIs(...)`; (c) `admin/importar.blade.php` → `action="{{ route('admin.importar') }}"` y `admin/usuarios.blade.php` → `route('admin.usuarios.crear')` / `route('admin.usuarios.actualizar', $u->id)`. `auth/login.blade.php` (`route('login')`), el logout (`route('logout')`) y los assets (`asset('css/app.css')` / `asset('js/juez.js')` con `?v=filemtime`) ya usaban helpers y no cambian. |
| **F19** | **Rutas con `route()` en controladores y en la raíz (sin paths escritos a mano)** | Todas las rutas pasan a tener nombre: la raíz `/` → `inicio`, la API → `api.grupos`, `api.participantes`, `api.guardar-orden`, `api.guardar-observacion`, `api.historial` y el POST de importación → `admin.importar.procesar`. Los `redirect('/…')` literales se sustituyen por helpers: `AuthController` (`redirect('/')` → `redirect()->route('inicio')`, `redirect()->intended('/')` → `intended(route('inicio'))`, `redirect('/login')` → `route('login')`), `JuezController` (`'/admin/resultados'` → `route('admin.resultados')`), `UsuarioController` (2× `'/admin/usuarios'` → `route('admin.usuarios')`) y el cierre de la raíz en `routes/web.php` (`'/admin/resultados' | '/juez'` → `route('admin.resultados') | route('juez')`). |
| **F20** | **Llamadas AJAX de `juez.js` inyectadas con `route()` desde el layout** | `layouts/app.blade.php` publica, antes del stack de scripts, `window.RUTAS = @json([...])` con las 6 URLs generadas por helpers: `grupos`, `participantes`, `guardarOrden`, `guardarObservacion`, `historial` (`route('api.historial', ['participante' => '__ID__'])`, marcador que el JS sustituye con `encodeURIComponent(id)`) y `pdf` (`route('resultados.pdf')`). `public/js/juez.js` elimina los literales `/api/…` y `/resultados/pdf` y usa `RUTAS.*`; `admin/importar.blade.php` apunta a `route('admin.importar.procesar')`; el test jsdom inyecta el mismo objeto `window.RUTAS` (mock idéntico al del layout). Con F18, **toda** URL del frontend (vistas, css, js) se genera con helpers. |


---

*Plan aprobado en modo Plan → Act. Fecha: 29/09/2026. Fixes F1–F3 añadidos el 29/09/2026. Fixes F4–F6 (seeder usuarios, gestión de usuarios, listado responsive) añadidos el 29/09/2026. Fixes F7–F10 (DNI antes del nombre y búsqueda por DNI, buscador en línea propia con ✕ al inicio de la lista, nivel/grado bajo IE-UGEL, observación al final) añadidos el 29/09/2026. Fixes F11–F12 (autoselección de categoría/clase única con carga de participantes, combo de puesto con filtro al tipear cuando hay más de 5 puestos) añadidos el 29/09/2026. Fixes F13–F15 (orden de filtros Categoría→Disciplina→Género→Clase→Prueba con buscador en dropdowns >5, dropdown Clase oculto si hay una sola clase, conteo de clasificados en cada opción de Prueba) añadidos el 29/09/2026. Fixes F16–F17 (altura del combo Disciplina/Prueba igual a los selects, valor visible y cierre del panel al elegir opción en el combo) añadidos el 29/09/2026. Fix F18 (rutas de las vistas con helpers route()/asset()/url() en vez de texto estático) añadido el 29/09/2026. Fixes F19–F20 (rutas con route() en controladores y en la raíz, llamadas AJAX de juez.js inyectadas con route() desde el layout) añadidos el 29/09/2026.*

