# Spec Delta

## ADDED Requirements

### Requirement: El botón "Detener" detiene la emisión
El botón "Detener" del panel de Restream SHALL detener el daemon que empuja al destino y SHALL dejar el destino en estado inactivo. La operación SHALL terminar solo cuando el proceso del daemon haya muerto, y SHALL matar además cualquier proceso ffmpeg que siga empujando al destino de ese destino.

#### Scenario: El daemon está vivo y el PID se conoce
- **WHEN** el operador pulsa "Detener" sobre un destino con un daemon en ejecución
- **THEN** el daemon recibe SIGTERM, el proceso desaparece y el destino queda `idle` con `pipeline_pid` en NULL

#### Scenario: El PID guardado se perdió
- **WHEN** el operador pulsa "Detener" y la fila del destino tiene `pipeline_pid` en NULL pero existe un daemon para ese destino
- **THEN** el sistema encuentra el daemon por su línea de órdenes, lo detiene y el destino queda `idle`

#### Scenario: El PID guardado apunta al hijo ffmpeg
- **WHEN** el `pipeline_pid` guardado corresponde al proceso ffmpeg y no al daemon
- **THEN** el sistema detiene el daemon (no solo el hijo) y ningún ffmpeg sigue empujando

### Requirement: La parada funciona entre usuarios del sistema
La parada SHALL funcionar aunque el daemon pertenezca a otro usuario del sistema que el proceso que la ordena. Cuando la señal no pueda entregarse, el sistema SHALL ordenarle al daemon que se detenga por otro medio y SHALL verificar de todas formas que el proceso terminó.

#### Scenario: La señal es rechazada por permisos
- **WHEN** el daemon corre como root y la orden de parada viene de un proceso `www`
- **THEN** el daemon recibe la orden por el canal alternativo, termina su ejecución y el destino queda `idle`

#### Scenario: El daemon no puede detenerse
- **WHEN** tras todos los intentos el proceso del daemon sigue vivo
- **THEN** el sistema NO informa de una parada exitosa: responde con error y un motivo legible, y el destino queda en estado de error

### Requirement: El daemon puede detenerse sin recibir una señal
El daemon SHALL comprobar periódicamente si el sistema le ordenó detenerse, y al recibir esa orden SHALL terminar de forma ordenada (detener su proceso ffmpeg, reportar su estado final y eliminar la orden).

#### Scenario: Llega la orden de parada por archivo
- **WHEN** el sistema escribe la orden de parada del daemon y el daemon la detecta
- **THEN** el daemon detiene su proceso ffmpeg, reporta que está inactivo y termina

### Requirement: No quedan emisiones invisibles
El sistema SHALL detectar y detener los daemons de restream cuyo destino ya no existe, y SHALL mantener registrado el PID real de los daemons vivos cuyo destino sí existe, de modo que ningún destino pueda seguir emitiendo sin que el panel lo muestre y pueda detenerlo.

#### Scenario: Daemon sin destino
- **WHEN** existe un daemon en ejecución cuyo destino no está en la base de datos
- **THEN** el sistema lo detiene y lo reporta

#### Scenario: Daemon con destino y PID perdido
- **WHEN** existe un daemon en ejecución cuyo destino sí existe pero su PID registrado no corresponde al proceso real
- **THEN** el sistema actualiza el PID registrado con el real

### Requirement: La ventana detenida por el operador no se reenciende
Cuando el operador detiene un destino cuya ventana de programación está vigente, el motor de ventanas SHALL considerar esa ventana detenida por el usuario y SHALL NOT volver a encenderla en los ticks siguientes.

#### Scenario: Tick posterior del motor de ventanas
- **WHEN** el motor de ventanas corre después de que el operador detuvo el destino
- **THEN** no vuelve a arrancar el daemon de esa ventana
