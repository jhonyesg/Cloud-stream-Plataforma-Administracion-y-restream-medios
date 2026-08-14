# Cloudstream Emission Daemon — Deployment Guide

## Requirements

- **OS**: Ubuntu 22.04+ (or any Linux with systemd)
- **Python**: 3.10+
- **GStreamer**: 1.20+ with plugins:
  - `gstreamer1.0-libav`
  - `gstreamer1.0-plugins-bad`
  - `gstreamer1.0-plugins-good`
  - `gstreamer1.0-plugins-ugly`
  - `gstreamer1.0-tools`
- **PHP/Laravel**: Same server or reachable via HTTP

## Installation

```bash
cd /www/wwwroot/cloudstream.mediaserver.com.co/emisor_python

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Verify GStreamer
python3 -c "import gi; gi.require_version('Gst', '1.0'); from gi.repository import Gst; Gst.init(None); print('OK')"
```

## Systemd Setup

```bash
# Create log directory
sudo mkdir -p /var/log/cloudstream-emission
sudo chown www:www /var/log/cloudstream-emission

# Copy unit file
sudo cp systemd/cloudstream-emission@.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Start a specific channel
sudo systemctl start cloudstream-emission@55555555-5555-5555-5555-555555555555

# Enable auto-start
sudo systemctl enable cloudstream-emission@55555555-5555-5555-5555-555555555555
```

## Laravel Integration

The daemon is spawned automatically when calling:
```
POST /api/channels/{id}/emission/start
```

Or manually via systemd for production stability.

## Rollback

```bash
# Stop all emission daemons
sudo systemctl stop 'cloudstream-emission@*'

# Or from Laravel:
POST /api/channels/{id}/emission/stop
```

## Log Rotation

Add to `/etc/logrotate.d/cloudstream-emission`:
```
/var/log/cloudstream-emission/*.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```
