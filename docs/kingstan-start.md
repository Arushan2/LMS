# kingstan start

This document explains how to run the LMS project for the first time and how to start it frequently after setup.

## 1. First-time setup

Run these commands only once when you first clone the project and configure the environment.

### 1.1 Backend environment setup

Open PowerShell and run:

```powershell
cd "d:\Project Uni\LMS\backend"
Copy-Item .env.example .env
```

Then update `backend/.env` with your MySQL connection details.

### 1.2 Frontend environment setup

```powershell
cd "d:\Project Uni\LMS\frontend"
Copy-Item .env.example .env
npm install
```

Update `frontend/.env` if you need any custom frontend environment variables.

### 1.3 Database creation and initialization

Create the MySQL database and bootstrap the initial data:

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php "d:\Project Uni\LMS\backend\scripts\init_db.php"
```

`init_db.php` creates the schema and inserts the temporary super admin account.

## 2. Frequent run commands

After initial setup, use these commands to start the backend API and frontend app.

### 2.1 Start backend API

From PowerShell:

```powershell
cd "d:\Project Uni\LMS\backend"
php -S localhost:8000 -t "d:\Project Uni\LMS\backend\public"
```

This command runs the PHP development server and serves the backend API on `http://localhost:8000`.

### 2.2 Start frontend app

In a separate terminal window:

```powershell
cd "d:\Project Uni\LMS\frontend"
npm run dev
```

This starts the Vite development server and opens the frontend at `http://localhost:5173` by default.

## 3. Summary

- First time:
  - `Copy-Item .env.example .env` in both `backend` and `frontend`
  - `npm install` in `frontend`
  - create the MySQL database
  - `php backend/scripts/init_db.php`
- Frequent run:
  - `php -S localhost:8000 -t backend/public`
  - `npm run dev` in `frontend`

## 4. Quick command list

### 4.1 Backend setup

```powershell
cd "d:\Project Uni\LMS\backend"
Copy-Item .env.example .env
```

### 4.2 Frontend setup

```powershell
cd "d:\Project Uni\LMS\frontend"
Copy-Item .env.example .env
npm install
```

### 4.3 Database creation and initialization

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php "d:\Project Uni\LMS\backend\scripts\init_db.php"
```

### 4.4 Start backend API

```powershell
cd "d:\Project Uni\LMS\backend"
php -S localhost:8000 -t "d:\Project Uni\LMS\backend\public"
```

### 4.5 Start frontend app

```powershell
cd "d:\Project Uni\LMS\frontend"
npm run dev
```

Use `kingstan start` as the document title for quick reference to project startup commands.