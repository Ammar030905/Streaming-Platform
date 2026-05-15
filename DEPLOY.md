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
| `DB_HOST`                       | `db.xxxxxxxxxxxx.supabase.co`              | ⚠️ Replace with your real Supabase host    |
| `DB_PORT`                       | `5432`                                     |                                            |
| `DB_NAME`                       | `postgres`                                 |                                            |
| `DB_USER`                       | `postgres`                                 |                                            |
| `DB_PASS`                       | `your-supabase-db-password`                | ⚠️ From Supabase Settings → Database       |
| `HLS_BASE_URL`                  | `https://your-stream-server.com/hls`       | Your RTMP/HLS server URL                   |

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
