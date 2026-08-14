# emission-timeline-consumption Specification

## Purpose

Consumo de la timeline diaria por el motor de emisión: reproducción de splits, offsets de fuente, duración efectiva, recarga versionada y recuperación exacta al reiniciar.

## Requirements

### Requirement: Consumir la timeline diaria como hoja de ruta
El daemon SHALL consumir los `program_timeline_items` ordenados por `starts_at_sec` y SHALL reproducir cada item una sola vez en el orden recibido, incluyendo cuñas y segmentos que compartan `media_item_id`.

#### Scenario: Reproducir película con cuña interna
- **WHEN** la timeline contiene segmento A, cuña y segmento B del mismo archivo
- **THEN** el daemon SHALL emitir A, luego la cuña y luego B sin reiniciar B desde el comienzo del archivo

### Requirement: Respetar offsets y duración efectiva
El consumidor SHALL iniciar cada segmento en `cue_in_sec`, detenerlo en `cue_out_sec` o al alcanzar `effective_duration_sec`, y SHALL usar la duración de la timeline como autoridad sobre la duración física del archivo.

#### Scenario: Reanudar desde el punto de corte
- **WHEN** el segmento posterior tiene `cue_in_sec=1800`
- **THEN** el archivo SHALL comenzar en el segundo 1800 y SHALL terminar al completar el segmento posterior

### Requirement: Recarga versionada sin alterar el item activo
Cuando una nueva versión de timeline esté disponible, el daemon SHALL conservar el item activo y SHALL aplicar la nueva secuencia desde un límite seguro posterior.

#### Scenario: Insertar cuña futura durante emisión
- **WHEN** se publica una nueva `timeline_version` con una cuña futura
- **THEN** el daemon SHALL reconocer la versión, SHALL mantener la emisión actual y SHALL reproducir la cuña cuando alcance su nuevo punto

### Requirement: Reinicio con recuperación exacta
Al reiniciar, el daemon SHALL resolver el item de la hora de emisión y SHALL aplicar el offset de fuente correspondiente, incluyendo segmentos posteriores de un split.

#### Scenario: Reiniciar durante el segundo segmento
- **WHEN** la emisión se reinicia mientras la película está en el segmento posterior a una cuña
- **THEN** el daemon SHALL comenzar en ese segmento y en su posición de fuente correspondiente, sin reproducir la primera parte ni la cuña nuevamente

### Requirement: Estado observable de la hoja de ruta
El daemon SHALL reportar `current_timeline_item_id`, `content_position_sec`, `mode` y `timeline_version` de forma consistente con el item realmente emitido.

#### Scenario: Estado durante una cuña
- **WHEN** la cuña está al aire
- **THEN** el estado SHALL identificar el item de tipo cue y SHALL reportar `mode=interrupt`
