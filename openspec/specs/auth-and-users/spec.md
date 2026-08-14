## Purpose
Authentication, authorization, and credential storage for the platform. Defines roles, sub-user hierarchy, user status, channel/folder scoping middleware, password hashing, audit logging, and the username-or-email login flow.
## Requirements
### Requirement: Roles de usuario admin y client
El sistema SHALL definir exactamente dos roles en el enum `user_role`: `admin` y `client`. Toda fila en `users` debe tener exactamente uno de estos dos valores.

#### Scenario: Admin tiene rol admin
- **WHEN** se crea un usuario y se asigna `role='admin'`
- **THEN** el middleware `EnsureRole:admin` lo deja pasar a rutas administrativas

#### Scenario: Cliente no accede a admin
- **WHEN** un usuario con `role='client'` intenta acceder a una ruta con `EnsureRole:admin`
- **THEN** el sistema responde HTTP 403

### Requirement: Sub-usuarios por cliente
El sistema SHALL permitir que un usuario (`role='client'`) tenga `owner_id` apuntando a otro usuario (`role='client'`) representando al cliente titular. La columna `owner_id` SHALL ser NULL para usuarios sin dueño (admins y clientes titulares).

#### Scenario: Operador pertenece a cliente titular
- **WHEN** existe `users(id=A, role='client')` y se crea `users(id=B, role='client', owner_id=A)`
- **THEN** toda query de B se filtra al mismo scope que A (canales y carpetas del cliente A)

#### Scenario: Cliente titular sin owner_id
- **WHEN** se crea un usuario con `role='client'` y `owner_id=NULL`
- **THEN** es el cliente titular y tiene alcance completo sobre sus canales propios

### Requirement: Estados de usuario
El sistema SHALL permitir dos estados en `user_status`: `active` (puede autenticarse y operar) y `suspended` (no puede autenticarse). Usuarios suspendidos SHALL recibir HTTP 401 con mensaje "Cuenta suspendida" al intentar login.

#### Scenario: Login de usuario activo
- **WHEN** un usuario con `status='active'` provee credenciales correctas
- **THEN** recibe sesión válida y redirige al dashboard

#### Scenario: Login de usuario suspendido
- **WHEN** un usuario con `status='suspended'` intenta autenticarse
- **THEN** el sistema rechaza el login con HTTP 401 y mensaje explícito

#### Scenario: Admin suspende a un cliente
- **WHEN** admin ejecuta acción sobre cliente, el sistema actualiza `status='suspended'`, `status_changed_at=now()`, `status_changed_by=admin.id`, `status_reason='texto'`
- **THEN** la fila se actualiza y `audit_logs` registra la acción

### Requirement: Middleware de alcance por canal
El sistema SHALL proporcionar un middleware `ScopeByChannel` que, para usuarios con `role='client'`, filtra automáticamente todas las queries de `channels`, `media_items`, `playlists`, `schedule_templates` a los canales donde `channels.owner_id` coincide con el usuario o su `owner_id`.

#### Scenario: Cliente ve solo sus canales
- **WHEN** un cliente con `id=X` (y `owner_id=NULL`) solicita `GET /api/channels`
- **THEN** la respuesta incluye solo canales donde `owner_id = X`

#### Scenario: Operador ve canales de su titular
- **WHEN** un usuario con `id=Y, owner_id=X` solicita `GET /api/channels`
- **THEN** la respuesta incluye los mismos canales que vería X

#### Scenario: Admin ve todos los canales
- **WHEN** un usuario con `role='admin'` solicita `GET /api/channels`
- **THEN** la respuesta no aplica filtro de scope

### Requirement: Middleware de alcance por carpeta
El sistema SHALL proporcionar un middleware `ScopeByFolder` que filtra queries de `media_items` a las carpetas cuyo `media_folders.channel_id` pertenece a un canal dentro del scope del usuario.

#### Scenario: Cliente ve solo medios de sus carpetas
- **WHEN** un cliente solicita `GET /api/media-items`
- **THEN** la respuesta excluye items cuyas carpetas tienen `channel_id` fuera de su scope

#### Scenario: Folder sin channel_id es solo admin
- **WHEN** existe `media_folders(channel_id=NULL)` y un cliente intenta listarla
- **THEN** la respuesta no la incluye

### Requirement: Credenciales hasheadas
El sistema SHALL almacenar la contraseña en `users.password` como hash bcrypt (`password_hash()` con `PASSWORD_BCRYPT`). Ninguna parte del sistema SHALL exponer ni loggear el valor plano.

#### Scenario: Hash al registrar
- **WHEN** admin crea un usuario con `password='miclave123'`
- **THEN** en DB se almacena un hash bcrypt de ~60 caracteres; el valor plano nunca se persiste

#### Scenario: Verificación en login
- **WHEN** un usuario envía `password` plano en el formulario de login
- **THEN** el sistema verifica con `password_verify()` y solo autentica si coincide con el hash

### Requirement: Auditoría de acciones
El sistema SHALL escribir en `audit_logs` toda acción de:

- Crear, actualizar o eliminar usuarios
- Crear, actualizar o eliminar canales
- Cambiar estado de usuario
- Vincular/desvincular carpetas a canales

Cada entrada SHALL incluir `user_id` (actor), `action` (verbo.entidad), `entity_type`, `entity_id`, `before` (jsonb del estado previo), `after` (jsonb del estado nuevo), `ip` y `at`.

#### Scenario: Crear canal queda auditado
- **WHEN** admin crea un canal vía API
- **THEN** `audit_logs` contiene una fila con `action='create.channel'`, `entity_id=nuevo_id`, `before=null`, `after={...canal completo...}`

#### Scenario: Cambiar role queda auditado
- **WHEN** admin cambia el `role` de un usuario
- **THEN** `audit_logs` registra `action='update.user.role'`, `before={role:'client'}`, `after={role:'admin'}`

### Requirement: Login con username o email
El sistema SHALL aceptar tanto `username` como `email` como identificador de login, tanto en el formulario web como en la API. El campo de entrada se llamará `login` (aceptando también `email` como alias retrocompatible en la API). El sistema SHALL buscar al usuario por `email` (case-insensitive) OR `username` (case-insensitive) y luego verificar la contraseña. El campo de la vista SHALL mostrar el label "Usuario o correo".

#### Scenario: Login con email
- **WHEN** un usuario envía `login=admin@cloudstream.local` con la contraseña correcta
- **THEN** el sistema autentica al usuario cuya `email` coincide (case-insensitive) y inicia sesión

#### Scenario: Login con username
- **WHEN** un usuario envía `login=Red Planner` con la contraseña correcta
- **THEN** el sistema autentica al usuario cuyo `username` coincide (case-insensitive) y inicia sesión

#### Scenario: Login con username en minúsculas
- **WHEN** un usuario envía `login=red planner` y el `username` almacenado es `Red Planner`
- **THEN** el sistema autentica correctamente (búsqueda case-insensitive)

#### Scenario: Login con credenciales incorrectas
- **WHEN** un usuario envía `login=admin` con una contraseña incorrecta
- **THEN** el sistema rechaza el login con mensaje "Credenciales inválidas." sin revelar si el identificador existe

#### Scenario: API login con campo email retrocompatible
- **WHEN** un cliente API envía `{"email": "admin@cloudstream.local", "password": "..."}` sin el campo `login`
- **THEN** el sistema acepta `email` como alias de `login` y autentica correctamente

#### Scenario: Login con usuario suspendido
- **WHEN** un usuario con `status='suspended'` envía credenciales correctas (username o email)
- **THEN** el sistema rechaza con HTTP 403 y mensaje "Cuenta suspendida."

#### Scenario: Throttle key usa el identificador normalizado
- **WHEN** un usuario falla el login 5 veces seguidas con `login=Red Planner`
- **THEN** el rate limiter bloquea posteriores intentos con la misma clave (username normalizado + IP), no solo por email

### Requirement: Auth screens expose the password via reveal toggle
The login (`/login`), register (`/register`), reset-password (`/reset-password`), and confirm-password screens SHALL render the password field through the shared `<x-password-input>` component. The profile update-password form SHALL render all three password fields (`current_password`, `password`, `password_confirmation`) through the same component. The reveal toggle SHALL let the user verify what they typed before submitting, which is critical on mobile devices and when typing long passphrases.

#### Scenario: Login reveals the password
- **WHEN** a user is on `/login` and clicks the eye toggle on the password field
- **THEN** the typed characters SHALL become visible, and clicking it again SHALL mask them. The form SHALL still submit on Enter and SHALL validate against `users.password` server-side.

#### Scenario: Profile change-password reveals all three fields
- **WHEN** a user is on `/profile` editing their password
- **THEN** the form SHALL render three password inputs (`current`, `new`, `confirm`) each with its own toggle, and the toggle on one SHALL NOT affect the visibility of the others.

#### Scenario: Register reveals the password before submit
- **WHEN** a user is on `/register` and reveals the password and password_confirmation fields
- **THEN** both fields SHALL become visible and SHALL remain independent (toggling one SHALL NOT toggle the other).

