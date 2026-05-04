# LMS Bug Fix #1 - Single Super Admin Restriction

## Summary
Fixed the bug where the system allowed multiple super admin accounts. Implemented a restriction to limit the system to have **only ONE super admin**. When a super admin already exists, the super_admin role is hidden from the signup form and new requests are rejected.

## Files Modified

### 1. Backend - src/AuthService.php
**Changes:**
- Modified `signup()` method (lines 24-27):
  - Added check to prevent super_admin role requests when one already exists
  - Returns error message: "The system can have only one super admin. That role is unavailable."

- Modified `canApprove()` method (lines 337-341):
  - Added check to prevent approving super_admin role when one already exists
  - Returns false to silently reject the approval

- Added `superAdminExists()` private method (lines 352-360):
  - Queries database for any active super_admin role assignment
  - Returns boolean indicating if super_admin exists

- Added `hasSuperAdmin()` public method (lines 362-365):
  - Public wrapper for superAdminExists() to be called from API

### 2. Backend - public/index.php
**Changes:**
- Added new API endpoint (lines 31-34):
  - `GET /api/auth/has-super-admin`
  - No authentication required
  - Returns: `{ ok: true, hasSuperAdmin: boolean }`

### 3. Frontend - src/App.jsx
**Changes:**
- SignupForm component (lines 30-72):
  - Added state: `hasSuperAdmin` - tracks if super admin exists
  - Added state: `rolesLoading` - tracks loading state
  - Added useEffect: Fetches super admin status on component mount
  - Added useMemo: Computes `availableRoles` filtering out super_admin if needed
  - Added useEffect: Safety check to change selected role if it becomes unavailable

- Role dropdown UI (lines 165-189):
  - Shows loading message while fetching role availability
  - Filters dropdown to show only available roles
  - Displays helper text when super_admin is unavailable

## Behavior Changes

### Before
- Multiple users could request and be assigned super_admin role
- Signup form showed all 4 roles regardless of existing super admins
- No system-level restriction

### After
- **When NO super admin exists:**
  - All 4 roles shown in signup dropdown
  - Users can request super_admin role
  - Approval creates the first (and only) super admin

- **When super admin exists:**
  - Only 3 roles shown in signup (super_admin hidden)
  - Attempts to request super_admin rejected with error
  - Attempts to approve super_admin rejected silently
  - Helper text explains the restriction

## How It Works

1. **Frontend loads** → Fetches `/api/auth/has-super-admin`
2. **Super admin exists?** 
   - YES → Filter roleOptions, hide super_admin from dropdown
   - NO → Show all roles
3. **User tries to signup with super_admin**
   - If one exists → Backend rejects with error message
   - If none exists → Signup proceeds normally
4. **User with super_admin role tries to approve super_admin request**
   - If one already exists → Approval rejected silently
   - If none exists → Approval succeeds, creating first super admin

## Testing Checklist

- [ ] Restart backend and frontend
- [ ] With NO super admins: Verify super_admin appears in dropdown
- [ ] Request super_admin role: Should succeed
- [ ] Approve request via ApprovalPanel
- [ ] Reload page: Verify super_admin disappears from dropdown
- [ ] Try to request super_admin again: Should see error message
- [ ] Try to create 2nd super_admin via API: Should fail
- [ ] Existing super admin can still manage other roles

## API Endpoints

### New Endpoint
- **GET /api/auth/has-super-admin**
  - Public (no auth required)
  - Response: `{ ok: true, hasSuperAdmin: boolean }`
  - Purpose: Determine which roles to display in signup form

## Database Changes
None required. Application-level logic enforces the restriction.

## Notes
- The restriction is enforced at both frontend (UI) and backend (API validation)
- Error handling is graceful - if role fetch fails, all roles are shown
- Existing super admins can still approve other role types
- For systems with multiple existing super admins, manual cleanup needed (see migration docs)

## Documentation
- Created: `/docs/changes-docs/2026-05-04-single-super-admin-restriction.md`
  - Detailed technical documentation
  - Verification steps
  - Migration guide for existing systems
