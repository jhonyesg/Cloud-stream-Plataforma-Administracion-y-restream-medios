## ADDED Requirements

### Requirement: Auditoría de cambio de contraseña
Toda vez que el hash de `users.password` se actualice mediante los flujos oficiales (self-service `PUT /password` o admin reset vía `PUT /admin/users/{user}`), el sistema SHALL escribir una fila en `audit_logs` con `action='update.user.password'`, `entity_type='user'`, `entity_id=<id del usuario afectado>`, `actor_id=<id del usuario que actuó (self=el mismo, admin=id del admin)>`, `before=NULL`, `after={"changed_by": "self" | "admin", "ip": "<ipv4 del request>"}`. La fila SHALL escribirse dentro de la misma transacción que el `UPDATE` sobre `users.password`; si la transacción aborta, la fila también se revierte. El sistema SHALL NEVER persistir el valor de la contraseña (plano ni hasheado) en `before` ni en `after`.

#### Scenario: Self-service password change auditado
- **WHEN** un usuario autenticado envía `PUT /password` con `current_password` correcto y `password` nuevo válido
- **THEN** `audit_logs` contiene una fila con `action='update.user.password'`, `entity_id=auth()->id()`, `actor_id=auth()->id()`, `after.changed_by='self'`, `after.ip=<ip>`, `before=NULL`, y la columna `users.password` del usuario es un nuevo hash bcrypt

#### Scenario: Admin reset auditado
- **WHEN** un admin envía `PUT /admin/users/{user}` con `password` no vacío
- **THEN** `audit_logs` contiene una fila con `action='update.user.password'`, `entity_id=<id del usuario afectado>`, `actor_id=<id del admin>`, `after.changed_by='admin'`, `before=NULL`

#### Scenario: Admin NO audita si deja password vacío
- **WHEN** un admin envía `PUT /admin/users/{user}` sin el campo `password` o con `password=''`
- **THEN** el sistema no escribe fila en `audit_logs` con `action='update.user.password'` (no hubo cambio de contraseña)

#### Scenario: Auditoría no contiene hash
- **WHEN** un cambio de contraseña es auditado
- **THEN** el campo `before` y el campo `after` nunca contienen una clave `password` ni un valor que parezca un bcrypt hash (`$2y$`, `$2a$`, `$2b$`)

### Requirement: Notificación por email al cambiar contraseña
Tras un cambio de contraseña exitoso el sistema SHALL encolar y enviar un email al usuario afectado (`users.email` actual) mediante el mailable `App\Mail\PasswordChangedNotification`. El email SHALL contener: fecha/hora del cambio, IP origen, y un enlace a `forgot-password` para el caso de que el usuario no reconozca la acción. Para cambios auto-iniciados (`self`) el asunto SHALL ser "Tu contraseña fue actualizada". Para cambios iniciados por admin (`admin`) el asunto SHALL ser "Tu contraseña fue restablecida por un administrador" y el cuerpo SHALL mencionar el identificador del admin que realizó la acción.

#### Scenario: Email enviado en self-service
- **WHEN** un usuario cambia su contraseña vía `PUT /password`
- **THEN** el sistema encola una instancia de `PasswordChangedNotification` hacia `users.email` con `changed_by='self'`

#### Scenario: Email enviado en admin reset
- **WHEN** un admin cambia la contraseña de otro usuario
- **THEN** el sistema encola una instancia de `PasswordChangedNotification` hacia `users.email` del usuario afectado con `changed_by='admin'` y el nombre del admin en el cuerpo

#### Scenario: Email no expone contraseñas
- **WHEN** el email se renderiza
- **THEN** el HTML y el texto plano NO contienen la contraseña del usuario (plano, hasheada, ni truncada)

### Requirement: Invalidación de sesiones tras cambio de contraseña
Tras un cambio de contraseña exitoso el sistema SHALL invalidar las sesiones activas del usuario afectado. Para self-service: la sesión actual del usuario se regenera (`session_id` nuevo) y todas las demás filas en `sessions` con `user_id=<id>` se eliminan. Para admin reset: TODAS las filas en `sessions` con `user_id=<id>` se eliminan, incluyendo la sesión actual del usuario afectado si la tenía. Adicionalmente, en ambos casos el sistema SHALL rotar el `remember_token` del usuario afectado (`Auth::user()->setRememberToken(...)` + save) para invalidar cookies "remember me" existentes.

#### Scenario: Self-service mantiene la sesión actual
- **WHEN** un usuario cambia su propia contraseña
- **THEN** la fila `sessions` correspondiente a su cookie actual se conserva (con un `session_id` regenerado) y todas las demás filas `sessions` con `user_id=<id>` se eliminan

#### Scenario: Admin reset cierra toda sesión
- **WHEN** un admin cambia la contraseña de un usuario
- **THEN** TODAS las filas `sessions` con `user_id=<id>` son eliminadas, sin importar qué dispositivo las creó

#### Scenario: Remember token rotado
- **WHEN** un cambio de contraseña ocurre
- **THEN** `users.remember_token` del usuario afectado cambia a un nuevo valor aleatorio, y cualquier cookie "remember me" emitida previamente ya no autentica

### Requirement: Rechazo de campos de contraseña en el endpoint de perfil
El endpoint `PATCH /profile` (controlado por `App\Http\Requests\ProfileUpdateRequest`) SHALL rechazar cualquier intento de enviar los campos `password`, `current_password` o `password_confirmation`, devolviendo HTTP 422 con errores de validación. La regla usada SHALL ser `prohibited` (no `prohibited_if`), de modo que el rechazo aplica tanto si el campo viene vacío como si viene con un valor. Ningún flujo del sistema SHALL persistir cambios de contraseña a través de `PATCH /profile`.

#### Scenario: password vacío en PATCH /profile es rechazado
- **WHEN** un usuario envía `PATCH /profile` con `password=''` (cadena vacía)
- **THEN** el sistema responde HTTP 422 con un mensaje "The password field is prohibited" en el bag de errores y no se ejecuta ningún `UPDATE` en `users`

#### Scenario: password con valor en PATCH /profile es rechazado
- **WHEN** un usuario envía `PATCH /profile` con `password='nuevaClave123'` y `current_password='viejaClave'`
- **THEN** el sistema responde HTTP 422 y la columna `users.password` no cambia

#### Scenario: PATCH /profile sin password funciona igual que antes
- **WHEN** un usuario envía `PATCH /profile` solo con `name` y `email` (caso legítimo)
- **THEN** el sistema responde 302 con flash `profile-updated` exactamente como antes
