# LMS User Account & Appointment Workflow

## 1) Role Hierarchy (Mandatory)

- `Super Admin` appoints `System Analyst`.
- `System Analyst` appoints `Lecturer` and `Student`.
- `Lecturer` and `Student` cannot appoint any roles.
- No self-appointment for privileged roles (`System Analyst`, `Super Admin`).

## 2) Access Model

Use RBAC with scope:
- Roles: `student`, `lecturer`, `system_analyst`, `super_admin`
- Scope examples: `institution`, `faculty`, `department`, `course`
- Core rule: permission = role permission + scope check

## 3) Account States

Each account moves through these states:

1. `invited` (invite created)
2. `pending_verification` (email/phone verification started)
3. `pending_approval` (waiting for appointing authority)
4. `active` (can log in and use LMS)
5. `suspended` (temporarily blocked)
6. `deactivated` (cannot access; data retained for audit)

## 4) Registration & Appointment Flows

### A) Student Account Creation and Appointment (by System Analyst)

1. Student submits sign-up request (`name`, `email`, `student id`, `program`, optional docs).
2. System creates user in `pending_approval` with temporary role request `student`.
3. System Analyst reviews request:
   - verifies student ID/institution records,
   - selects scope (institution/faculty/program/year),
   - approves or rejects.
4. On approval:
   - role assignment created (`student` + scope),
   - account set to `pending_verification`.
5. Student verifies email and sets password + MFA (optional for students, recommended).
6. Account becomes `active`.

Reject path:
- status -> `deactivated` or `rejected` (optional custom state),
- user receives reason and re-apply link.

### B) Lecturer Account Creation and Appointment (by System Analyst)

1. System Analyst creates lecturer invite OR lecturer submits request.
2. Required data: `name`, `official email`, `employee number`, `department`, `teaching scope`.
3. System Analyst validates HR/department data.
4. On approval:
   - assign `lecturer` role with scope (`department`, `courses`, `semester`),
   - set status `pending_verification`.
5. Lecturer accepts invite, verifies email, sets password, enables MFA (recommended mandatory for staff).
6. Account becomes `active`.

### C) System Analyst Appointment (by Super Admin)

1. Super Admin initiates appointment from Admin Console.
2. Inputs: `name`, `official email`, `department`, `permission scope`.
3. System enforces policy:
   - cannot self-appoint,
   - optional two-person approval if enabled,
   - mandatory MFA at first login.
4. Super Admin confirms appointment.
5. System sends secure invite token (short expiry, one-time use).
6. Analyst verifies identity, sets password, enrolls MFA.
7. Account becomes `active` with `system_analyst` permissions.

### D) Super Admin Creation

Recommended: bootstrap only once via secure deployment process.
After bootstrap:
- new super admins should require dual authorization from existing super admins.

### E) Temporary Static Bootstrap (Current Phase)

For the current implementation phase, keep a static first super-admin credential:

- bootstrap email: `rockarush2@gmail.com`
- bootstrap password: `1234abcd`

Bootstrap rules (temporary):
- Static credential is used only to create/activate the first `super_admin` account.
- At first successful login, force password change immediately.
- Enforce MFA setup for `super_admin` before any appointment actions.
- Once first `super_admin` is active, continue normal hierarchy flow.
- Do not use this static bootstrap policy for production long-term; migrate to one-time token later.

## 5) Lifecycle Operations

### Role Change

- Only appointing authority or higher can change roles:
  - `Super Admin` can change any role.
  - `System Analyst` can assign/remove only `student` and `lecturer`.
- Every role change writes an immutable audit log.

### Suspension

- Authority:
  - `System Analyst`: suspend `student` / `lecturer` in their scope.
  - `Super Admin`: suspend any user.
- Suspension requires reason and optional end date.

### Deactivation

- Triggered by graduation, resignation, policy breach, or inactivity rules.
- Keep records for compliance; remove active sessions immediately.

### Re-activation

- Must follow fresh approval by the same authority chain.

## 6) Approval and Escalation Rules

- Student/Lecturer requests pending more than X days -> escalate to assigned System Analyst queue.
- Analyst appointment pending more than X days -> escalate to Super Admin queue.
- Rejected requests must include reason category and free-text note.

## 7) Security & Audit Requirements

- Password hashing with modern algorithm.
- MFA mandatory for `system_analyst` and `super_admin`; recommended for `lecturer`.
- Session revocation on role changes/suspension.
- Audit events (minimum):
  - `invite_created`
  - `appointment_approved`
  - `appointment_rejected`
  - `role_assigned`
  - `role_removed`
  - `account_suspended`
  - `account_deactivated`
  - `account_reactivated`

## 8) API Workflow Blueprint (Suggested)

- `POST /auth/signup` (student self-request)
- `POST /admin/invites` (invite lecturer/analyst)
- `POST /admin/appointments/{id}/approve`
- `POST /admin/appointments/{id}/reject`
- `POST /auth/invite/accept`
- `POST /auth/verify-email`
- `POST /admin/users/{id}/suspend`
- `POST /admin/users/{id}/deactivate`
- `POST /admin/users/{id}/reactivate`
- `POST /admin/users/{id}/roles`

## 9) BPMN-Style Logical Flow (Text)

1. Request/Invite Created
2. Validation Check
3. Approver Decision by hierarchy
4. If approved -> verification + credential setup
5. If verification complete -> activate
6. If rejected -> close request with reason
7. Continuous: audit, notification, SLA escalation

## 10) Non-Negotiable Constraints from Your Requirement

- `Student` and `Lecturer` appointment authority = `System Analyst`.
- `System Analyst` appointment authority = `Super Admin`.
- Any exception path must route upward, never downward.

## 11) Course Module Appointment Mapping

- Course module creation authority = `System Analyst`.
- Per course module, `Student` and `Lecturer` appointments are done by `System Analyst`.
- `Super Admin` handles policy override and governance escalation only.
- Course appointment rights must follow scope checks (`institution`/`department`/`course`).

## 12) Interim Policy Note

- Current phase allows static bootstrap for first `super_admin` only.
- Hierarchy remains unchanged: `super_admin` -> `system_analyst` -> `lecturer` / `student`.
- Future phase should replace static bootstrap with one-time setup token.
