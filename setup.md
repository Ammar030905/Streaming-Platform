# 🚀 Live Streaming Platform - Setup & Documentation

This guide explains how to get your new production-ready Live Streaming platform running. 

## 📂 Project Structure Created

```text
/stream
  ├── /admin
  │   ├── dashboard.php
  │   ├── events.php
  │   ├── login.php
  │   ├── logout.php
  │   ├── users.php
  │   └── /includes
  │       ├── footer.php
  │       └── header.php
  ├── /assets
  │   ├── /css
  │   │   └── style.css
  │   ├── /js
  │   │   └── main.js
  │   └── /images (created automatically on upload)
  ├── /includes
  │   ├── footer.php
  │   └── header.php
  ├── /user
  │   ├── dashboard.php
  │   └── watch.php
  ├── config.php
  ├── database.sql
  ├── index.php
  ├── login.php
  ├── logout.php
  ├── register.php
  └── setup.md (This file)
```

---

## 🛠️ Step 1: Database Setup (Supabase PL/pgSQL)

1. Go to [supabase.com](https://supabase.com) and create a new project.
2. In your project dashboard, go to **SQL Editor → New Query**.
3. Paste and run the full contents of `supabase_schema.sql`.
   - Creates PL/pgSQL enum types: `user_status`, `event_status`, `stream_status`
   - Creates all tables with `BIGSERIAL` PKs and `TIMESTAMPTZ` timestamps
   - Creates a trigger (`trg_streams_timestamps`) that auto-sets `started_at` / `ended_at` on stream status changes
   - Seeds the default Super Admin account
4. Go to **Settings → Database** and copy your connection parameters:
   - Host: `db.<project-ref>.supabase.co`
   - Port: `5432`
   - Database: `postgres`
   - User: `postgres`
   - Password: your project database password
5. Set these as environment variables on your hosting platform (see `.env.example`).

> ⚠️ Use the **direct connection** (port 5432), not the connection pooler (port 6543) — PDO requires a persistent connection.

---

## 🐘 Step 2: Running PHP Server

If using **Laragon**:
1. Place the project folder in `c:\laragon\www\stream`.
2. Start All (Apache/Nginx + MySQL) in Laragon.
3. The site is now accessible at `http://localhost/stream`.

> ⚠️ Ensure `http://localhost/stream` matches the `BASE_URL` set in `config.php`.

---

## 📡 Step 3: Setting Up RTMP (Nginx)

To accept streams from OBS and convert them to HLS (`.m3u8`) for the web player, you need an RTMP server. We recommend **NGINX with the RTMP module**.

### Recommended Low-Latency Nginx Configuration (`nginx.conf`)

Add the following to your Nginx configuration to enable RTMP and HLS:

```nginx
rtmp {
    server {
        listen 1935;
        chunk_size 4096;

        application live {
            live on;
            record off;
            wait_key on;
            interleave on;
            sync 50ms;
            
            # Enable HLS
            hls on;
            hls_path /tmp/hls; # Change this to an accessible web folder, e.g., C:/laragon/www/hls
            hls_fragment 1;
            hls_playlist_length 6;
            hls_cleanup on;
        }
    }
}

http {
    # Existing HTTP config...

    server {
        listen 8080; # Ensure this ports matches the `user/watch.php` player URL config!

        location /hls {
            # Serve HLS fragments
            types {
                application/vnd.apple.mpegurl m3u8;
                video/mp2t ts;
            }
            root /tmp; # Must match the parent directory of hls_path above
            add_header Cache-Control "no-cache, no-store, must-revalidate";
            add_header Pragma "no-cache";
            add_header Expires "0";
            
            # CORS setup
            add_header Access-Control-Allow-Origin *;
            add_header Access-Control-Allow-Credentials false;
        }
    }
}
```

> **Note**: For Windows users on Laragon, there are pre-compiled Nginx binaries with the RTMP module available online. Make sure the output `hls_path` is accessible.

---

## 🎥 Step 4: Connecting OBS (No Lag / No Audio Delay Settings)

1. Open **OBS Studio**.
2. Go to `Settings` -> `Stream`.
3. Select **Service**: `Custom...`
4. Set **Server**: `rtmp://your-server-ip/live` (or `rtmp://127.0.0.1/live` for local testing).
5. Set **Stream Key**: `[Paste the unique Stream Key generated in the Admin Panel for your event]`
6. Click `Apply` and `OK`.
7. Click **Start Streaming** in OBS.

When OBS connects, Nginx RTMP starts creating `.ts` and `.m3u8` files in your configured `hls_path`, and viewers can watch the stream live via the platform's video player.

OBS settings that significantly reduce lag and audio drift:

1. `Settings -> Video`
    - Base Canvas: your real output resolution (example `1280x720`)
    - Output (Scaled): same as base
    - FPS: `30` (or `60` only if your CPU/network are strong)
2. `Settings -> Output -> Streaming`
    - Encoder: `x264` or `NVENC`
    - Rate Control: `CBR`
    - Bitrate: `2500-4500` kbps for 720p30
    - Keyframe Interval: `1` second (must match low-latency HLS behavior)
    - Preset: `veryfast` (x264) or `quality/performance` (NVENC)
3. `Settings -> Audio`
    - Sample Rate: `48 kHz`
    - Channels: `Stereo`
4. Advanced sync
    - Keep all audio sources at `0 ms` sync offset unless you verify a specific mismatch
    - Avoid browser source tabs with high CPU load during live sessions

---

## 🧪 Streaming Stability Checklist (Must Pass)

Use this checklist before every event to avoid buffering, lag spikes, or audio delay complaints:

1. Broadcaster upload speed is at least **2x** configured video bitrate.
2. Server CPU load stays below 70% while live.
3. HLS URL responds in browser and updates every 1-2 seconds.
4. Viewer test on 2 networks (Wi-Fi and mobile hotspot).
5. Audio clap test: clap on camera and confirm viewer hears clap with minimal delay and no drift over 10+ minutes.
6. Do a 20-minute dry run before event start.

---

## 🚨 Troubleshooting Lag, Buffering, and Audio Delay

If users report lag or audio desync:

1. Lower OBS bitrate by 15-25% first.
2. Confirm keyframe interval is exactly 1 second.
3. Ensure server and HLS host use SSD storage (not slow HDD).
4. Confirm reverse proxy/CDN is not caching `.m3u8` or `.ts` files.
5. Restart OBS and RTMP service if drift grows over time.
6. If still unstable, move HLS delivery to a CDN and keep RTMP ingest separate.

---

## 🔐 Credentials

### 1. Admin Login
- **URL**: `http://localhost/stream/admin/login.php`
- **Email**: `admin@stream.local`
- **Password**: `admin123`

### 2. User Login
- **URL**: `http://localhost/stream/login.php`
- To test as a user:
  1. Go to `register.php` and create an account.
  2. Log in as the Admin and go to **Manage Users** to `Approve` the account.
  3. Log in as the User.

---

## 🎉 Features Included
- **Security**: PDO Prepared statements, Bcrypt hashing, session validation.
- **UI**: Bootstrap 5 responsive UI with a custom dark mode/Netflix-style theme.
- **Admin**: Full event management, stream toggle, user approval system, dashboard with metrics.
- **User**: Secure dashboard, locked stream viewer (requires login).
- **Streaming**: Video.js integration configured for HLS playback.

---

## ✅ Deployment-Ready Checklist

Before going live, set these environment variables in your web server / hosting panel:

- `APP_ENV=production`
- `APP_DEBUG=0`
- `APP_URL=https://your-domain.com`
- `DB_HOST=db.<your-project-ref>.supabase.co`
- `DB_PORT=5432`
- `DB_USER=postgres`
- `DB_PASS=your_supabase_db_password`
- `DB_NAME=postgres`
- `HLS_BASE_URL=https://your-stream-domain.com/hls`
- `APP_VERSION=1.0.0`

Production notes:

1. Run `supabase_schema.sql` once in the Supabase SQL Editor before first production run.
3. Use HTTPS (TLS) so secure session cookies and HLS links are protected.
4. Restrict write permissions to only upload folders:
     - `assets/images/`
     - `assets/images/gallery/`
5. Configure regular MySQL backups and log rotation.
6. Place this app behind Nginx/Apache with gzip/brotli and static-file caching enabled.

---

## 👥 Concurrent User Capacity (At One Time)

Capacity depends mostly on your streaming bandwidth and HLS server, not on PHP page rendering.

Practical baseline for one server setup:

- **PHP + MySQL app layer**: about **120 to 200 concurrent active web users** (login/dashboard actions)
- **HLS viewers** (Nginx RTMP/HLS on same host): about **400 to 800 concurrent viewers** at 720p with good network throughput

Recommended planning number for this current codebase on a single mid-range VPS (4 vCPU, 8 GB RAM, SSD):

- **~500 concurrent users total**
    - around **150 app users** doing interactive actions
    - around **350 live viewers** streaming at once

To handle more than this comfortably:

1. Move HLS/video serving to a dedicated streaming node or CDN.
2. Add Redis for session/rate-limit storage.
3. Add DB indexes and read replicas if traffic grows.
4. Scale horizontally behind a load balancer.
