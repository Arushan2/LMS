# startBavanuja — LMS startup & operation documentary

This document explains how the frontend, backend and database for this LMS collaborate and includes exact, copy-paste commands (PowerShell) for initial setup, day-to-day operations, smoke tests, troubleshooting and production notes.

Use this as the canonical runbook for developers working on the project.

---

## Quick architecture overview

- Frontend: React + Vite in `frontend/`.
  - Dev server (hot reload) runs with `npm run dev`.
  - Production build via `npm run build`.
  - The frontend calls the backend API (configured via an env var).

- Backend: PHP app rooted at `backend/public/index.php`.
  - The PHP built-in server can be used for development (`php -S`), or use Apache / nginx + PHP-FPM for production.
  - Entrypoint exposes API routes such as:
    - GET  /api/health
    - POST /api/auth/signup
    - POST /api/auth/signin
    - GET  /api/auth/me
    - GET  /api/approvals/pending
    - POST /api/approvals/{id}/review
  - Backend reads configuration from `backend/.env` (copy from `.env.example`) using `src/env.php`.

- Database: SQL schema in `backend/sql/schema.sql`. There is a convenience script `backend/scripts/init_db.php` that:
  - loads the `.env` values,
  - executes the SQL schema file,
  - creates or activates a bootstrap super admin (email and password taken from `.env`).

---

## Contract (short)
- Inputs: HTTP requests from the frontend and from dev/test tools.
- Outputs: JSON responses and static assets for the frontend.
- Error modes to watch: DB connection failures, missing/invalid env vars, CORS issues, port conflicts, and file-sync issues when working inside OneDrive.

---

## Important env vars (copy these into `backend/.env`)
Excerpted from `backend/.env.example` — change secrets to production-safe values:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=lms
DB_USER=root
DB_PASS=

APP_ENV=local
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
TOKEN_TTL_HOURS=8

BOOTSTRAP_SUPERADMIN_EMAIL=rockarush2@gmail.com
BOOTSTRAP_SUPERADMIN_PASSWORD=1234abcd
```

Notes:
- `FRONTEND_URL` is used by `backend/public/index.php` to set the Access-Control-Allow-Origin header for CORS in dev.
- `BOOTSTRAP_SUPERADMIN_*` values are used by `backend/scripts/init_db.php` to create a super admin user after schema import.

Frontend env (from `frontend/.env.example`):
```
VITE_API_BASE_URL=http://localhost:8000/api
```
Make sure the `VITE_API_BASE_URL` points to the running backend host and port.

---

## One-time setup (PowerShell commands)
Open PowerShell at the repository root: `c:\Users\kbanu\OneDrive\Documents\GitHub\LMS`

1) Copy example env files (backend + frontend)

```powershell
Copy-Item -Path ".\backend\.env.example" -Destination ".\backend\.env"
# frontend example may not exist; run safely
Copy-Item -Path ".\frontend\.env.example" -Destination ".\frontend\.env" -ErrorAction SilentlyContinue
```

2) Edit the copied files and update credentials:
- `backend/.env`: set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS and optionally FRONTEND_URL and APP_URL.
- `frontend/.env`: set `VITE_API_BASE_URL` to match the backend (e.g. `http://localhost:8000/api`).

3) Install frontend dependencies

```powershell
cd .\frontend
npm install
cd ..
```

4) (Optional) Install composer dependencies for backend if used

```powershell
# Only if a composer.json exists in backend/
cd .\backend
if (Test-Path composer.json) { composer install }
cd ..
```

5) Initialize database schema

Two options: (A) run the SQL directly using mysql CLI, or (B) run the included PHP init script.

A) Using MySQL CLI (replace user/password as needed):

```powershell
# Create database (if not exists) and import schema
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p lms < .\backend\sql\schema.sql
```

B) Using the PHP helper script (reads `backend/.env` for DB credentials):

```powershell
php .\backend\scripts\init_db.php
```

The PHP script executes the SQL schema and prepares a bootstrap super-admin using `BOOTSTRAP_SUPERADMIN_EMAIL` and `BOOTSTRAP_SUPERADMIN_PASSWORD` from `.env`.

6) Confirm DB user created

```powershell
mysql -u root -p -e "USE lms; SHOW TABLES;"
```

---

## First run (development)
You will run backend and frontend dev servers concurrently (two terminals)

1) Start backend dev server (terminal A)

```powershell
cd .\backend
# Serve the public folder on port 8000
php -S localhost:8000 -t public
```

Keep this terminal open to view PHP errors and request logs.

2) Start frontend dev server (terminal B)

```powershell
cd .\frontend
npm run dev
```

Vite will show a URL (e.g. `http://localhost:5173`). Open it in your browser. The frontend should call the backend using the `VITE_API_BASE_URL` value.

---

## Daily / usual commands

Frontend

```powershell
# Start dev server
cd .\frontend
npm run dev

# Build for production
npm run build

# Preview built site locally
npm run preview
```

Backend

```powershell
# Start PHP built-in dev server
cd .\backend
php -S localhost:8000 -t public

# Restart: stop and re-run the command
```

Database

```powershell
# Import schema
mysql -u root -p lms < .\backend\sql\schema.sql

# Dump / backup
mysqldump -u root -p lms > .\backend\sql\lms_dump.sql
```

Logs & tailing (PowerShell)

```powershell
# Adjust the path if your app writes logs elsewhere
Get-Content .\backend\storage\logs\app.log -Tail 50 -Wait
```

---

## Useful API smoke tests (PowerShell examples)
Replace host/port if you use different values.

1) Health check

```powershell
Invoke-RestMethod -Method Get -Uri "http://localhost:8000/api/health"
```

Expected JSON: { "ok": true, "service": "lms-auth-api" }

2) Sign up (example)

```powershell
$body = @{ fullName = 'Dev User'; email = 'dev@example.test'; password = 'password123'; requestedRole = 'instructor' } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8000/api/auth/signup" -ContentType 'application/json' -Body $body
```

3) Sign in

```powershell
$body = @{ email = 'dev@example.test'; password = 'password123' } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8000/api/auth/signin" -ContentType 'application/json' -Body $body
```

The signin response contains authentication info (token). Use the bearer token in subsequent calls:

```powershell
$headers = @{ Authorization = "Bearer $token" }
Invoke-RestMethod -Method Get -Uri "http://localhost:8000/api/auth/me" -Headers $headers
```

4) Pending approvals (requires authorized super admin / user)

```powershell
Invoke-RestMethod -Method Get -Uri "http://localhost:8000/api/approvals/pending" -Headers @{ Authorization = "Bearer $token" }
```

5) Review an approval

```powershell
$body = @{ action = 'approve'; note = 'Looks good' } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8000/api/approvals/123/review" -Headers @{ Authorization = "Bearer $token" } -ContentType 'application/json' -Body $body
```

---

## Troubleshooting checklist
- DB connection error:
  - Verify `backend/.env` DB_* values.
  - Confirm MySQL is running and reachable from the host.
  - Test with `mysql -u DB_USER -p -h DB_HOST -P DB_PORT`.

- CORS / Access-Control-Allow-Origin:
  - The backend sets CORS origin from `FRONTEND_URL` in `backend/.env`; update it for dev or allow `*` temporarily.

- Port conflict:
  - Change PHP built-in server port (e.g. `php -S localhost:8001 -t public`) and update `VITE_API_BASE_URL`.

- OneDrive sync issues:
  - Working inside OneDrive may cause file locks or delays. Consider moving active dev work to a non-synced folder.

- Missing composer dependencies:
  - Run `composer install` inside `backend/` if `composer.json` exists.

- PHP errors:
  - Check the console running `php -S` and the PHP error logs configured on your system. Add `display_errors=1` in development php.ini cautiously.

---

## Small maintenance & recommended commands
- Rebuild frontend assets before publishing:

```powershell
cd .\frontend
npm ci
npm run build
```

- Backup DB before migrations:

```powershell
mysqldump -u root -p lms > .\backups\lms_$(Get-Date -Format yyyyMMdd).sql
```

- Re-run the bootstrap script when you need to recreate the super admin (it is idempotent for the user email):

```powershell
php .\backend\scripts\init_db.php
```

---

## Production notes (brief)
- Use a production web server (nginx or Apache + PHP-FPM) not `php -S`.
- Serve built frontend static assets from the webserver or a CDN.
- Keep `.env` out of version control and use secrets manager in production.
- Use HTTPS everywhere and secure cookies & JWTs.
- Run DB backups and enable monitoring.

---

## Final checks & quality gates (quick triage you can run now)
- `cd frontend && npm run build` — should finish with no errors.
- `php -S localhost:8000 -t backend/public` — no parse errors on start.
- `Invoke-RestMethod -Method Get -Uri 'http://localhost:8000/api/health'` — returns ok JSON.

---

## Where to go next
- If you want, this document can be expanded to include:
  - exact expected API request/response JSON schemas,
  - CI/CD deployment steps,
  - health checks and monitoring setup.

---

Created for: the `bavanuja-branch1` development flow. Keep `docs/startBavanuja.md` updated when env names, ports, or scripts change.

Run backend properly


1 Start My sql server in power shell as administatornet(right click in the powershell name on search bar and choose run as administrator) start MySQL80
net start MySQL80


2 Create the MySQL database

Open MySQL and run:

```sql
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
cd backend
php scripts\init_db.php

Start api
php -S localhost:8000 -t public

In the ending should close all the terminal for frontend and backend. then stop mysql server with powersell run as administator option. 

net stop MySQL80


