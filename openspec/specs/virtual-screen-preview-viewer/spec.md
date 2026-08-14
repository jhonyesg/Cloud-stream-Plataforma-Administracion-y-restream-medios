## ADDED Requirements

### Requirement: Visor dedicado muestra el PNG a tamaño natural sobre fondo oscuro
El sistema SHALL abrir un modal visor (`virtual-screen-preview-viewer`) cuando el usuario hace clic en **Vista previa** desde el editor de Pantalla Virtual. El visor SHALL mostrar el PNG generado por `POST /api/virtual-screens/{channelId}/test-preview` en su tamaño natural (encajado con `object-contain` sobre fondo oscuro `#0b1230` para evocar la pantalla en negro de una emisión). El visor SHALL mostrar como metadatos la resolución real del canvas (`width × height`) y la fecha/hora de generación.

#### Scenario: Visor abre con la imagen del preview
- **WHEN** el usuario hace clic en el botón **Vista previa** del editor y la generación del PNG responde 200 OK
- **THEN** el modal del editor SHALL quedar en el stack de modales y el visor SHALL abrirse encima mostrando el PNG centrado sobre fondo oscuro con su resolución real visible como metadato

#### Scenario: Visor muestra la fecha de generación
- **WHEN** el visor está abierto
- **THEN** SHALL mostrarse la fecha y hora en que se generó el PNG actual (formato legible, localizado)

#### Scenario: Visor se cierra con Esc o con el botón de cerrar
- **WHEN** el usuario pulsa `Esc` o hace clic en el botón de cerrar del visor
- **THEN** el visor SHALL cerrarse, el modal del editor SHALL volver a estar visible y todo su estado (logo, sliders, ffmpeg preview) SHALL permanecer intacto

### Requirement: Visor expone acciones de descargar y regenerar
El visor SHALL mostrar una barra de acciones inferior con al menos: **Descargar PNG** y **Regenerar**. **Descargar PNG** SHALL iniciar la descarga del archivo `preview.png` usando el blob URL actual. **Regenerar** SHALL volver a llamar al endpoint `POST /api/virtual-screens/{channelId}/test-preview` con la configuración vigente en el editor y reemplazar el blob URL mostrado en el visor; mientras la regeneración esté en curso SHALL mostrarse un estado de carga en el botón.

#### Scenario: Descargar PNG descarga el archivo correcto
- **WHEN** el usuario hace clic en **Descargar PNG**
- **THEN** SHALL iniciarse una descarga del archivo con nombre `preview.png` correspondiente al blob URL actual del visor

#### Scenario: Regenerar actualiza la imagen del visor
- **WHEN** el usuario hace clic en **Regenerar** mientras el visor está abierto
- **THEN** el visor SHALL mostrar un estado de carga, SHALL llamar al endpoint de preview y SHALL reemplazar el blob URL anterior por el nuevo; el blob URL anterior SHALL ser liberado

#### Scenario: Regenerar muestra error si el endpoint falla
- **WHEN** el usuario hace clic en **Regenerar** y el endpoint responde con error (4xx/5xx)
- **THEN** el visor SHALL mostrar el mensaje de error devuelto por el servidor y SHALL mantener el PNG anterior visible

### Requirement: Visor libera el blob URL al cerrarse
El visor SHALL liberar (`URL.revokeObjectURL`) cualquier blob URL activo cuando el modal se cierre, para evitar acumulación de memoria en el navegador.

#### Scenario: Blob URL se libera al cerrar el visor
- **WHEN** el visor se cierra (por `Esc`, botón de cerrar, o stack de modales)
- **THEN** el blob URL actualmente mostrado SHALL ser liberado