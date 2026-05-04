# Single Super Admin Restriction Implementation

## Change Summary
Implemented a system-wide restriction to limit the LMS to have **only ONE super admin** account. When a super admin already exists, the super_admin role option is hidden from the signup form dropdown, and the system rejects any attempts to create additional super admin accounts.

## Changes Made

### Backend Changes

#### 1. AuthService.php - New Methods
- **`superAdminExists(): bool`** - Checks if any active super_admin role assignment exists in the system
- **`hasSuperAdmin(): bool`** - Public wrapper for the above method to be used by API endpoints
- **Modified `canApprove()`** - Prevents approval of super_admin role requests if one already exists
- **Modified `signup()`** - Rejects super_admin role requests during signup if one already exists

#### 2. Backend API Endpoint
- **`GET /api/auth/has-super-admin`** - New public endpoint that returns `{ ok: true, hasSuperAdmin: boolean }`
  - Available to all users (no authentication required)
  - Used by frontend to determine which roles to display

#### 3. Error Messages
- When attempting to request super_admin role and one exists:
  - **Signup error**: "The system can have only one super admin. That role is unavailable."
  - **Approval error**: Approval is silently rejected if super_admin already exists

### Frontend Changes

#### 1. SignupForm Component Enhancement
- **New state**: `hasSuperAdmin` and `rolesLoading` flags
- **New effect hook**: Fetches super admin status on component mount via `/api/auth/has-super-admin`
- **Computed roles**: `availableRoles` - Filters out super_admin role if one exists
- **Updated UI**: 
  - Shows loading message while fetching role availability
  - Displays available roles only (super_admin hidden when one exists)
  - Shows helper text: "👑 Super Admin role is not available (system limit: 1 only)."

## Behavior

### Before Changes
- Multiple users could request and be assigned the super_admin role
- Frontend showed all 4 roles in signup form regardless of existing super admins
- No system-level restriction on super admin count

### After Changes
- **When NO super admin exists**:
  - Signup form shows all 4 roles (student, lecturer, system_analyst, super_admin)
  - Users can request super_admin role
  - First approval creates the system super admin

- **When ONE super admin exists**:
  - Signup form shows 3 roles only (student, lecturer, system_analyst)
  - Super admin role is hidden with helper message
  - Signup attempts with super_admin role are rejected with error message
  - Approval attempts for super_admin are rejected silently

## How to Verify

1. **Initial Setup**:
   - Delete any existing super_admin assignments: `DELETE FROM user_role_assignments WHERE role_name = 'super_admin';`
   - Reload frontend - super_admin option should appear in signup dropdown

2. **Create First Super Admin**:
   - Sign up new account requesting super_admin role
   - Approve the request via ApprovalPanel
   - User now has super_admin role

3. **Verify Restriction**:
   - Reload frontend - super_admin option should be hidden
   - Try to sign up another account requesting super_admin - should see error message
   - Try to approve another super_admin request - should be silently rejected

## Database Schema
No database schema changes were required. The unique constraint approach was considered but avoided for compatibility reasons. Application-level logic handles the restriction.

## API Backward Compatibility
- All existing endpoints remain unchanged
- New endpoint `/api/auth/has-super-admin` is additive only
- Frontend gracefully handles both old and new behavior

## Data Migration
For existing systems with multiple super admins:
1. Identify which super admin should be the single authorized one
2. Deactivate other super admin assignments: `UPDATE user_role_assignments SET is_active = 0 WHERE role_name = 'super_admin' AND user_id NOT IN (selected_super_admin_id);`
3. Or delete them: `DELETE FROM user_role_assignments WHERE role_name = 'super_admin' AND user_id NOT IN (selected_super_admin_id);`

## Technical Notes
- The `hasSuperAdmin()` check is performant (single row check with LIMIT 1)
- Frontend caches role availability during SignupForm lifetime
- Validation happens at both signup and approval stages
- No session or authentication required for role availability check

