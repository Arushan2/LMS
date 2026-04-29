# Initial Security Fixes (29 Apr 2026)

This document summarizes the security and QA fixes applied to the initial LMS authentication and approval flow.

## Summary of changes

- Removed hardcoded super-admin credentials from UI and docs.
- Switched frontend auth from localStorage to HttpOnly cookie-based sessions.
- Added server-side logout with token revocation.
- Implemented login rate limiting with lockout window (per email + IP).
- Tightened approval access: non-approvers receive 403 instead of empty list.
- Limited bootstrap super-admin creation to local env and required explicit credentials.

## Backend changes

1) Cookie auth support
- API now sets an HttpOnly auth cookie on successful sign-in.
- API reads auth token from Authorization header or cookie.
- CORS now allows credentials.

2) Login rate limiting
- New login_attempts table added to schema.
- Failed sign-ins are tracked and locked when thresholds are exceeded.
- Defaults are configurable via env variables.

3) Logout endpoint
- New POST /api/auth/logout endpoint deletes server-side token and clears cookie.

4) Approval authorization
- Pending approvals endpoint now returns 403 for users without reviewer roles.

5) Local-only bootstrap
- Bootstrap super-admin is created only when APP_ENV=local.
- Credentials must be explicitly provided in .env.

## Frontend changes

- Sign-in no longer stores token in localStorage.
- Requests use cookie-based auth with credentials: include.
- Sign-out calls /api/auth/logout and clears session state.
- Sign-in helper text no longer exposes credentials.

## Environment variables

Add or confirm these settings in backend .env:

- LOGIN_MAX_ATTEMPTS (default 5)
- LOGIN_WINDOW_MINUTES (default 15)
- LOGIN_LOCK_MINUTES (default 15)
- BOOTSTRAP_SUPERADMIN_EMAIL (local only)
- BOOTSTRAP_SUPERADMIN_PASSWORD (local only)

## Database update

After pulling changes, run:

- php backend/scripts/init_db.php

This applies the updated schema and creates login_attempts.

## API additions

- POST /api/auth/logout

## Verification checklist

- Sign-in succeeds and sets HttpOnly cookie.
- Refreshing the page keeps the user signed in.
- Sign-out invalidates the session and cookie.
- Non-approver sees 403 on GET /api/approvals/pending.
- Repeated failed sign-ins trigger a 429 response.

