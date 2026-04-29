# LMS Initial Version (React + Vite + PHP + MySQL)

This initial version implements role-based `signup` and `signin` with your planned hierarchy:

- `super_admin` appoints `system_analyst`
- `system_analyst` appoints `lecturer` and `student`
- all roles can submit signup requests
- access is granted after approval and role assignment

## Project Structure

- `frontend/` - React + Vite app
- `backend/` - PHP API
- `backend/sql/schema.sql` - MySQL schema
- `backend/scripts/init_db.php` - database init + bootstrap super admin
- `docs/workflows/` - workflow docs

## Implemented Features

- Signup endpoint for all roles (`student`, `lecturer`, `system_analyst`, `super_admin`)
- Signin endpoint with token-based auth
- Profile endpoint (`/auth/me`)
- Pending approvals endpoint for approvers
- Approve/Reject endpoint with hierarchy checks
- React UI for signup/signin + approval dashboard

## Bootstrap Super Admin (current temporary policy)

Bootstrap credentials are set via backend env in local development only.
`backend/scripts/init_db.php` skips bootstrapping outside `APP_ENV=local`.

## Prerequisites

- Node.js 18+
- PHP 8.1+
- MySQL 8+

## Setup

### 1) Backend env

Copy env template and adjust DB values:

```bash
cp /Users/arushan/Downloads/LMS/backend/.env.example /Users/arushan/Downloads/LMS/backend/.env
```

Add bootstrap credentials for local development in `.env`:

```bash
BOOTSTRAP_SUPERADMIN_EMAIL=you@example.com
BOOTSTRAP_SUPERADMIN_PASSWORD=choose-a-strong-password
```

### 2) Create MySQL database

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3) Initialize schema and bootstrap admin

```bash
php /Users/arushan/Downloads/LMS/backend/scripts/init_db.php
```

### 4) Run backend API

```bash
php -S localhost:8000 -t /Users/arushan/Downloads/LMS/backend/public
```

### 5) Frontend env and install

```bash
cp /Users/arushan/Downloads/LMS/frontend/.env.example /Users/arushan/Downloads/LMS/frontend/.env
cd /Users/arushan/Downloads/LMS/frontend
npm install
```

### 6) Run frontend

```bash
cd /Users/arushan/Downloads/LMS/frontend
npm run dev
```

Open `http://localhost:5173`.

## API Summary

- `GET /api/health`
- `POST /api/auth/signup`
- `POST /api/auth/signin`
- `GET /api/auth/me`
- `GET /api/approvals/pending`
- `POST /api/approvals/{id}/review`

## Notes

- This is an initial auth/appointment version; course/module operations can be added next.
- Current auth token is DB-backed bearer token with expiry.
- Approval rules are enforced server-side.
