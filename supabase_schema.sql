-- ============================================================
-- MIGRATION: add new columns to users if upgrading existing DB
-- Safe to run even if columns already exist
-- ============================================================
DO $$ BEGIN
    ALTER TABLE users ADD COLUMN IF NOT EXISTS session_token   VARCHAR(64)  DEFAULT NULL;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at   TIMESTAMPTZ  DEFAULT NULL;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS last_logout_at  TIMESTAMPTZ  DEFAULT NULL;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS last_ip         VARCHAR(45)  DEFAULT NULL;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS last_user_agent TEXT         DEFAULT NULL;
    ALTER TABLE users ADD COLUMN IF NOT EXISTS login_count     INT          NOT NULL DEFAULT 0;
END $$;

-- Run in: Supabase Dashboard > SQL Editor > New Query
-- ============================================================

-- ------------------------------------------------------------
-- ENUM TYPES
-- ------------------------------------------------------------
DO $$ BEGIN
    CREATE TYPE user_status AS ENUM ('pending', 'approved', 'rejected');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
    CREATE TYPE event_status AS ENUM ('upcoming', 'live', 'ended');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
    CREATE TYPE stream_status AS ENUM ('offline', 'online');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ------------------------------------------------------------
-- TABLES
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admins (
    id      BIGSERIAL PRIMARY KEY,
    username VARCHAR(50)  NOT NULL,
    email    VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS users (
    id              BIGSERIAL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'user',
    status          user_status  NOT NULL DEFAULT 'pending',
    session_token   VARCHAR(64)  DEFAULT NULL,
    last_login_at   TIMESTAMPTZ  DEFAULT NULL,
    last_logout_at  TIMESTAMPTZ  DEFAULT NULL,
    last_ip         VARCHAR(45)  DEFAULT NULL,
    last_user_agent TEXT         DEFAULT NULL,
    login_count     INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS events (
    id            BIGSERIAL PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    description   TEXT,
    schedule_date TIMESTAMPTZ  NOT NULL,
    thumbnail     VARCHAR(255),
    status        event_status NOT NULL DEFAULT 'upcoming',
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS streams (
    id           BIGSERIAL PRIMARY KEY,
    event_id     BIGINT       NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    stream_key   VARCHAR(50)  NOT NULL UNIQUE,
    status       stream_status NOT NULL DEFAULT 'offline',
    viewer_count INT          NOT NULL DEFAULT 0,
    started_at   TIMESTAMPTZ,
    ended_at     TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS logs (
    id         BIGSERIAL PRIMARY KEY,
    action     VARCHAR(255) NOT NULL,
    details    TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id         BIGSERIAL PRIMARY KEY,
    stream_id  BIGINT      NOT NULL REFERENCES streams(id) ON DELETE CASCADE,
    user_id    BIGINT      NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
    message    TEXT        NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS gallery (
    id         BIGSERIAL PRIMARY KEY,
    title      VARCHAR(200),
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

-- ------------------------------------------------------------
-- INDEXES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS watch_sessions (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    stream_id  BIGINT      NOT NULL REFERENCES streams(id) ON DELETE CASCADE,
    event_id   BIGINT      NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_ping  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ended_at   TIMESTAMPTZ DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_watch_user_id   ON watch_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_watch_stream_id ON watch_sessions(stream_id);
CREATE INDEX IF NOT EXISTS idx_watch_last_ping ON watch_sessions(last_ping DESC);

CREATE INDEX IF NOT EXISTS idx_streams_event_id    ON streams(event_id);
CREATE INDEX IF NOT EXISTS idx_streams_stream_key  ON streams(stream_key);
CREATE INDEX IF NOT EXISTS idx_chat_stream_id      ON chat_messages(stream_id);
CREATE INDEX IF NOT EXISTS idx_logs_created_at     ON logs(created_at DESC);

-- ------------------------------------------------------------
-- FUNCTION + TRIGGER: auto-set started_at / ended_at on streams
-- ------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_streams_timestamps()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.status = 'online' AND OLD.status = 'offline' THEN
        NEW.started_at := NOW();
        NEW.ended_at   := NULL;
    ELSIF NEW.status = 'offline' AND OLD.status = 'online' THEN
        NEW.ended_at := NOW();
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_streams_timestamps ON streams;
CREATE TRIGGER trg_streams_timestamps
    BEFORE UPDATE OF status ON streams
    FOR EACH ROW
    EXECUTE FUNCTION fn_streams_timestamps();

-- ------------------------------------------------------------
-- SEED: default super-admin  (password = 'admin123')
-- ------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM admins WHERE email = 'admin@stream.local') THEN
        INSERT INTO admins (username, email, password)
        VALUES (
            'SuperAdmin',
            'admin@stream.local',
            '$2y$10$4YMS..WPYJUJXZ4H2QUa4eqavoHp0W4Kcg7LCZaAz.4rrP8stSCo6'
        );
    END IF;
END;
$$;
