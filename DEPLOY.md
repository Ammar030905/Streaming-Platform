# 🚀 Deployment Checklist — Streaming Platform

---

## STEP 1 — Get Your Supabase Credentials

1. Go to https://supabase.com and open your project
2. Navigate to: **Project Settings → Database**
3. Scroll to **Connection parameters** (NOT the connection pooler)
4. Copy these exact values:

   | Field    | Where to find it                        |
   |----------|-----------------------------------------|
   | Host     | looks like: db.xxxxxxxxxxxx.supabase.co |
   | Port     | 5432                                    |
   | Database | postgres                                |
   | User     | postgres                                |
   | Password | the password you set when creating the project |

> ⚠️ Use port **5432** (direct connection). Do NOT use port 6543 (pooler).

---

## STEP 2 — Run the Database Schema on Supabase

1. In your Supabase project go to: **SQL Editor → New Query**
2. Open the file `supabase_schema.sql` from this project
3. Paste the entire contents into the SQL Editor
4. Click **Run**
5. You should see all tables created: `admins`, `users`, `events`, `streams`, `logs`, `chat_messages`, `gallery`

---

## STEP 3 — Set Environment Variables on Render

1. Go to https://dashboard.render.com
2. Open your **streaming-platform** web service
3. Go to **Environment** tab
4. Add each variable below with its exact value:

---

## ✅ Required Environment Variables

| Variable                        | Value                                      | Notes                                      |
|---------------------------------|--------------------------------------------|--------------------------------------------|
| `APP_ENV`                       | `production`                               |                                            |
| `APP_DEBUG`                     | `0`                                        | Never set to 1 in production               |
| `APP_URL`                       | `https://streaming-platform-1-3jgm.onrender.com` | Your exact Render URL, no trailing slash   |
| `APP_VERSION`                   | `1.0.0`                                    | Increment on each deploy for cache busting |
| `DB_HOST`                       | `db.vizqjooqkjmynjkgfdtx.supabase.co`     |                                            |
| `DB_PORT`                       | `5432`                                     |                                            |
| `DB_NAME`                       | `postgres`                                 |                                            |
| `DB_USER`                       | `postgres`                                 |                                            |
| `DB_PASS`                       | `your-supabase-db-password`                | ⚠️ From Supabase Settings → Database       |
| `HLS_BASE_URL`                  | `https://your-stream-origin-or-cdn.com/hls`| Dedicated streaming origin or CDN endpoint |

> Render should host the PHP web app only. Do not point `HLS_BASE_URL` at Render, localhost, or any URL served by the app container.

---

## STEP 4 — Redeploy on Render

After setting all env variables:

1. Go to your service on Render
2. Click **Manual Deploy → Deploy latest commit**
3. Watch the logs — you should NOT see any database errors
4. Visit your URL: https://streaming-platform-1-3jgm.onrender.com

---

## STEP 5 — Verify It Works

- [ ] Home page loads without "Service temporarily unavailable"
- [ ] Go to `/admin/login.php` — log in with `admin@stream.local` / `admin123`
- [ ] Go to `/register.php` — register a test user
- [ ] In admin panel, approve the test user
- [ ] Log in as the test user at `/login.php`

## LIVE STREAMING ARCHITECTURE

For reliable playback, split the system into two tiers:

1. Web tier: Render hosts the PHP app, admin panel, login, and watch pages.
2. Streaming tier: a separate HLS origin or managed live platform serves the `.m3u8` playlist and `.ts` segments.

Recommended streaming backends:

1. Managed: Mux, Cloudflare Stream, or AWS IVS.
2. Self-hosted: Nginx RTMP/LL-HLS on a separate VPS, then put a CDN in front of it.

For a community event and around 50 concurrent viewers, a managed streaming backend is the safest choice. If you self-host, keep the origin off Render and enable CORS plus no-cache headers on the HLS paths.

## STREAMING STABILITY BASELINE

Use these baseline settings for consistent long-duration telecasts:

1. HLS segment duration: `1s` to `2s`.
2. Playlist window: `6s` to `12s`.
3. OBS keyframe interval: `1s`.
4. OBS bitrate for 720p: `2500-4500 kbps` with CBR.
5. Audio sample rate: `48kHz`, stereo.
6. Keep stream origin and CDN clocks synchronized via NTP.

## OPERATIONS AND MONITORING

Before each event:

1. Run a 20-minute dry run with at least two viewer devices.
2. Validate the exact event playlist URL from your app: `HLS_BASE_URL/<stream_key>.m3u8`.
3. Confirm the playlist updates every 1-2 seconds.
4. Verify origin CPU, memory, and egress have headroom above 30%.

During live event:

1. Monitor active viewers from admin dashboard and CDN traffic metrics.
2. Watch for repeated player reconnects or segment 404/5xx spikes.
3. Keep one operator on OBS and one operator on platform moderation.

After event:

1. Archive logs with request IDs.
2. Record incident notes (time, symptom, mitigation).
3. Update runbook with any recurring issue signatures.

## SCALING PLAN

To scale beyond basic usage:

1. Keep app tier and stream tier isolated.
2. Put HLS behind a CDN (Cloudflare, Fastly, CloudFront).
3. Use Postgres connection pooling and monitor slow queries.
4. Move sessions/rate limits to Redis for multi-instance app scaling.
5. Add synthetic monitoring for `/admin/login.php`, `/user/dashboard.php`, and a sample event playlist URL.

---

## 🔐 Default Admin Credentials

| Field    | Value               |
|----------|---------------------|
| URL      | `/admin/login.php`  |
| Email    | `admin@stream.local`|
| Password | `admin123`          |

> ⚠️ Change the admin password immediately after first login in production.

---

## 🛠️ Troubleshooting

| Error                                      | Fix                                                              |
|--------------------------------------------|------------------------------------------------------------------|
| `could not translate host name`            | `DB_HOST` is wrong — copy the exact host from Supabase Settings |
| `password authentication failed`           | `DB_PASS` is wrong — reset it in Supabase Settings → Database   |
| `Service is temporarily unavailable`       | `APP_DEBUG=1` temporarily to see the real error in browser      |
| `relation "users" does not exist`          | You haven't run `supabase_schema.sql` yet — do Step 2           |
| `500` on all pages after schema run        | Check all env vars are saved and redeploy                        |
| Black video / endless buffering             | `HLS_BASE_URL` is offline, wrong, or missing CORS/HLS files. Use a dedicated streaming origin and verify the `.m3u8` URL directly in a browser. |
