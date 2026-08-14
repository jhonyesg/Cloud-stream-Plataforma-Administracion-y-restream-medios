## ADDED Requirements

### Requirement: GStreamer pipeline supports transcode mode
The system SHALL build a GStreamer pipeline that decodes, scales, re-encodes, and muxes to RTMP when `VirtualScreen.codec_video != "copy"` or `codec_audio != "copy"`.

#### Scenario: Transcode pipeline construction
- **WHEN** `VirtualScreen` specifies `codec_video=libx264`, `width=1280`, `height=720`, `video_bitrate_kbps=1000`, `fps=30`
- **THEN** the pipeline is:
  `uridecodebin → queue → videoconvert → videoscale → capsfilter(I420,1280x720,30fps) → x264enc(bitrate=1000,tune=zerolatency,speed-preset=veryfast) → queue → flvmux → rtmpsink`
  and symmetrically for audio: `uridecodebin → queue → audioconvert → audioresample → capsfilter(44100,2ch) → avenc_aac(bitrate=128000) → queue → flvmux`
- **AND** the `flvmux` has `streamable=true`
- **AND** the `rtmpsink` has `location="{output_url} live=1"`

#### Scenario: Transcode gapless restart
- **WHEN** the current item ends and the next item (or loop restart) begins
- **THEN** the daemon calls `pipeline.seek_simple(TIME, FLUSH|ACCURATE, 0)` on the source
- **AND** the encoder (`x264enc`) and muxer (`flvmux`) remain in `PLAYING` state
- **AND** the RTMP stream does not disconnect

### Requirement: GStreamer pipeline supports copy mode
The system SHALL build a GStreamer pipeline that passthroughs H.264 and AAC directly to RTMP when `VirtualScreen.codec_video == "copy"` and `codec_audio == "copy"`.

#### Scenario: Copy pipeline construction
- **WHEN** `VirtualScreen` specifies `codec_video=copy`, `codec_audio=copy`
- **THEN** the pipeline is:
  `filesrc → qtdemux → queue → h264parse(config-interval=-1) → queue → flvmux → rtmpsink`
  and symmetrically for audio: `qtdemux → queue → aacparse → queue → flvmux`
- **AND** no decode or re-encode elements are present

#### Scenario: Copy gapless restart
- **WHEN** the source reaches EOS
- **THEN** the daemon calls `pipeline.seek_simple(TIME, FLUSH|ACCURATE, 0)`
- **AND** `qtdemux` re-reads the file from the beginning without unlinking downstream pads
- **AND** the muxer continues without disconnection

### Requirement: Optional logo overlay
The system SHALL overlay a logo image on the video when `VirtualScreen.logo_media_item_id` is set, respecting `logo_x`, `logo_y`, `logo_w`, `logo_h`, and `logo_opacity`.

#### Scenario: Logo overlay in transcode mode
- **WHEN** a logo media item is configured
- **THEN** the pipeline inserts `gdkpixbufoverlay` after `videoconvert` and before the encoder
- **AND** the overlay uses the logo file path as `location`, with `offset-x`, `offset-y`, `overlay-width`, `overlay-height`, and `alpha` derived from the config

#### Scenario: Logo overlay in copy mode
- **WHEN** a logo media item is configured but the pipeline is in copy mode
- **THEN** the pipeline MUST switch to transcode mode (logo overlay requires raw video)
- **AND** the system logs a warning: "Logo overlay forces transcode mode"

### Requirement: Missing file handling
The system SHALL detect when a `media_item` file is missing or unreadable and transition to error state.

#### Scenario: File missing during playback
- **WHEN** the pipeline tries to open `channel.root_path/filename` and receives `RESOURCE_NOT_FOUND`
- **THEN** the daemon posts an ERROR bus message
- **AND** the daemon sets `emission_state.status = error`, `error_message = "Archivo no encontrado: {filename}"`
- **AND** the pipeline stops (does not auto-retry)

### Requirement: Fallback when channel is offline
When emission is not running, the system MAY emit a fallback stream if `VirtualScreen.fallback_type` is configured. This is handled by a separate lightweight process or static loop, not by the main daemon.

#### Scenario: Black fallback
- **WHEN** `fallback_type = black`
- **THEN** a 1x1 black frame is streamed at the configured resolution and bitrate

#### Scenario: Test pattern fallback
- **WHEN** `fallback_type = test_pattern`
- **THEN** a GStreamer `videotestsrc` pattern (e.g., `smpte`) is streamed
