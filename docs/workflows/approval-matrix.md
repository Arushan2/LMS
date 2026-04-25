# LMS Approval Matrix

## Role Appointment Authority

| Target Role      | Who Can Appoint     | Who Can Revoke      | Notes |
|------------------|---------------------|---------------------|-------|
| `student`        | `system_analyst`    | `system_analyst` or `super_admin` | Scope-limited by institution/department |
| `lecturer`       | `system_analyst`    | `system_analyst` or `super_admin` | Must validate staff identity |
| `system_analyst` | `super_admin`       | `super_admin`       | MFA mandatory |
| `super_admin`    | `super_admin` (dual approval recommended) | `super_admin` (policy-based) | Highest privilege role |

## Permission Boundary

| Actor            | Can Manage Students | Can Manage Lecturers | Can Manage Analysts | Can Manage Super Admins |
|------------------|---------------------|----------------------|---------------------|--------------------------|
| `student`        | No                  | No                   | No                  | No                       |
| `lecturer`       | No                  | No                   | No                  | No                       |
| `system_analyst` | Yes (in scope)      | Yes (in scope)       | No                  | No                       |
| `super_admin`    | Yes                 | Yes                  | Yes                 | Yes (policy rules)       |

## Course Module Governance Authority

| Action | Primary Authority | Optional Override | Notes |
|--------|-------------------|-------------------|-------|
| Create course module | `system_analyst` | `super_admin` (exception only) | Module metadata and publication control |
| Publish course module | `system_analyst` | `super_admin` (exception only) | Must pass validation checks |
| Appoint lecturer to module | `system_analyst` | `super_admin` (escalation only) | Check lecturer status and scope |
| Appoint student to module | `system_analyst` | `super_admin` (escalation only) | Check student status, prerequisites, capacity |
| Remove/reassign module appointments | `system_analyst` | `super_admin` (escalation only) | Requires reason and audit entry |

## Course Appointment Boundary

| Actor | Can Create Module | Can Assign Lecturer to Module | Can Assign Student to Module |
|-------|-------------------|-------------------------------|------------------------------|
| `student` | No | No | No |
| `lecturer` | No | No | No |
| `system_analyst` | Yes | Yes (in scope) | Yes (in scope) |
| `super_admin` | Governance only | Override only | Override only |

## Recommended SLA

- Student/Lecturer appointment decision: within `2 business days`
- System Analyst appointment decision: within `3 business days`
- Urgent suspension response: within `4 hours`

## Minimum Notifications

- Invite sent
- Approval granted/rejected
- Account activated
- Role changed
- Account suspended/deactivated
