# Running the LMS Project

This document explains how to start the LMS project for the first time and how to run it repeatedly during development. It covers the frontend, backend, and database.

## 1. Project overview

- `frontend/` contains the React + Vite application.
- `backend/` contains the PHP API and database initialization.
- `backend/sql/schema.sql` defines the MySQL database schema.
- `backend/scripts/init_db.php` creates the database schema and bootstraps the default super admin.

## 2. Prerequisites

Before starting, install:

- Node.js 18 or later
- PHP 8.1 or later
- MySQL 8 or later
- A terminal or command prompt

If you use Windows, PowerShell works well.

## 3. Initial setup

### 3.1 Backend environment file

Create a backend `.env` file from the example:

PowerShell:

```powershell
Copy-Item backend\.env.example backend\.env
```

Then open `backend\.env` and confirm these values:

- `DB_HOST` - database host, usually `127.0.0.1`
- `DB_PORT` - database port, usually `3306`
- `DB_NAME` - database name, usually `lms`
- `DB_USER` - database user, usually `root`
- `DB_PASS` - database password
- `APP_URL` - backend URL, default `http://localhost:8000`
- `FRONTEND_URL` - frontend URL, default `http://localhost:5173`

The file also contains bootstrap admin credentials.

### 3.2 Create the MySQL database

Open MySQL and run:

```sql
CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

If you use the command line:

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3.3 Install frontend dependencies

From the repo root:

```powershell
cd frontend
npm install
```

### 3.4 Initialize the database and bootstrap super admin

From the repo root:

```powershell
cd backend
php scripts\init_db.php
```

This script reads `backend/.env`, creates the required tables, and inserts or updates the default `super_admin` account.

### 3.5 Start the backend API

From the repo root:

```powershell
cd backend
php -S localhost:8000 -t public
```

This launches the backend API at `http://localhost:8000`.

### 3.6 Start the frontend app

In a separate terminal from the repo root:

```powershell
cd frontend
npm run dev
```

Then open the frontend at `http://localhost:5173`.

## 4. Continuous development workflow

During development, you will usually run the backend and frontend together with the database.

### 4.1 Start services each session

Use two terminal windows:

- Terminal 1: backend server
- Terminal 2: frontend dev server

Example:

Terminal 1:

```powershell
cd e:\projects\LMS\backend
php -S localhost:8000 -t public
```

Terminal 2:

```powershell
cd e:\projects\LMS\frontend
npm run dev
```

### 4.2 Frontend live reload

The Vite dev server automatically reloads when you edit files under `frontend/src`.

### 4.3 Backend changes

The PHP built-in server does not automatically reload PHP files, but changes are applied on page refresh. Save files in `backend/src` and refresh the frontend to see updates.

### 4.4 Re-run database initialization

If you need to re-create the database schema or refresh the bootstrap account, run:

```powershell
cd backend
php scripts\init_db.php
```

This is useful after schema changes or when resetting local data.

## 5. Development guidance

### 5.1 Backend code

- `backend/public/index.php` is the API entry point.
- `backend/src/AuthService.php` contains auth, signup, signin, and approval logic.
- `backend/src/Database.php` manages the MySQL connection.
- `backend/src/helpers.php` includes request and response helpers.

### 5.2 Frontend code

- `frontend/src/App.jsx` is the main React component.
- `frontend/src/main.jsx` bootstraps the app.
- `frontend/src/styles.css` contains styling.

### 5.3 Important environment behavior

- The backend reads `backend/.env` using `backend/src/env.php`.
- CORS is enabled for `FRONTEND_URL` from the env file.
- The frontend expects the backend API on `http://localhost:8000`.

## 6. API reference

Common endpoints:

- `GET /api/health` — service status
- `POST /api/auth/signup` — register user requests
- `POST /api/auth/signin` — login
- `GET /api/auth/me` — authenticated user info
- `GET /api/approvals/pending` — pending approval requests
- `POST /api/approvals/{id}/review` — approve or reject a request

## 7. Troubleshooting

### Backend cannot connect to MySQL

- Verify MySQL is running.
- Check `backend/.env` for correct `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`.

### Frontend cannot call backend

- Confirm `php -S localhost:8000 -t public` is running.
- Confirm `backend/.env` has `FRONTEND_URL=http://localhost:5173`.
- Open browser dev tools and verify CORS or network errors.

### Port conflict

If `localhost:8000` or `localhost:5173` is already in use, change the backend or frontend port:

- Backend: `php -S localhost:9000 -t public`
- Frontend: add `--port 4173` to `npm run dev` or edit `vite.config.js` if needed.

## 8. Recommended start order

1. Start MySQL.
2. Start the backend.
3. Start the frontend.
4. Open `http://localhost:5173`.

---

This guide is designed to make local development smooth and repeatable for the LMS project.