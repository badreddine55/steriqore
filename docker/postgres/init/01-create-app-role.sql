-- Row-Level Security only works as a real second line of defence if the
-- role the running application connects as is NOT a superuser and does NOT
-- own the tables: Postgres superusers and table owners bypass RLS entirely,
-- regardless of ENABLE/FORCE ROW LEVEL SECURITY. The POSTGRES_USER created by
-- the official postgres image (here: "saw") owns the whole cluster, so it
-- must be reserved for migrations/admin tasks only. The app connects as this
-- restricted role instead. See docs/erd.md section 4 and CLAUDE.md's
-- tenancy invariant.
DO
$$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'steriqore_app') THEN
        CREATE ROLE steriqore_app LOGIN PASSWORD 'steriqore_app_secret' NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
    END IF;
END
$$;

GRANT CONNECT ON DATABASE saw TO steriqore_app;
GRANT USAGE ON SCHEMA public TO steriqore_app;

-- Tables/sequences created later by migrations (run as the superuser) won't
-- automatically grant to steriqore_app — ALTER DEFAULT PRIVILEGES covers
-- every table the superuser creates from here on.
ALTER DEFAULT PRIVILEGES FOR ROLE saw IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO steriqore_app;
ALTER DEFAULT PRIVILEGES FOR ROLE saw IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO steriqore_app;

-- Also grant on whatever already exists at init time (harmless if empty).
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO steriqore_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO steriqore_app;
