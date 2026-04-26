# LMS Bootstrap Super Admin Policy (Temporary)

## 1) Current-Phase Bootstrap Credential

For the current stage only, first super-admin bootstrap uses:

- email: `rockarush2@gmail.com`
- password: `1234abcd`

## 2) Allowed Usage

- This credential is only for first `super_admin` account activation.
- It must not be used to create normal users.
- It must not bypass role hierarchy.

## 3) Mandatory First Login Actions

1. Force immediate password change.
2. Enroll MFA before accessing appointment functions.
3. Record audit event: `bootstrap_super_admin_login`.

## 4) Hierarchy After Bootstrap

- `super_admin` appoints `system_analyst`.
- `system_analyst` appoints `lecturer` and `student`.
- Course module creation and course appointments remain under `system_analyst` authority.

## 5) Temporary Nature

- This is an interim policy.
- Future target is one-time bootstrap token flow.
- Static credential method should be removed before production launch.

## 6) Related Documents

- `docs/workflows/user-account-appointment-workflow.md`
- `docs/workflows/approval-matrix.md`
- `docs/workflows/course-module-workflow.md`
