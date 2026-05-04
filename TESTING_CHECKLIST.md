# LMS Bug Fixes - Testing Checklist

## Test Environment Setup
- [ ] Backend running on `http://localhost:8000`
- [ ] Frontend running on `http://localhost:5173`
- [ ] Database synced with latest schema
- [ ] All code changes pulled and deployed

---

## BUG FIX #1: Single Super Admin Restriction

### Scenario 1: Fresh Installation (No Super Admin Exists)
- [ ] Delete any existing super_admin assignments from database:
  ```sql
  DELETE FROM user_role_assignments WHERE role_name = 'super_admin';
  ```
- [ ] Reload signup page
- [ ] Verify super_admin option appears in role dropdown (should see 4 roles)
- [ ] Request account with super_admin role
- [ ] Sign out and sign in as existing super admin (or wait for approval)
- [ ] Navigate to Approvals panel
- [ ] Approve the super_admin role request
- [ ] Reload signup page
- [ ] Verify super_admin option disappears (should see 3 roles)
- [ ] Verify helper text shows: "👑 Super Admin role is not available (system limit: 1 only)."

### Scenario 2: Verify Error Message When Super Admin Exists
- [ ] (With super admin already existing)
- [ ] Try to request super_admin role in signup form
- [ ] Should see error: "The system can have only one super admin. That role is unavailable."
- [ ] Verify form submission is blocked

### Scenario 3: Prevent Approval of Second Super Admin
- [ ] Create two super_admin role requests (via API or manual)
- [ ] Approve the first one
- [ ] As the approved super admin, try to approve the second one
- [ ] Second approval should fail silently (not appear in list or show error)
- [ ] Verify only one super admin exists in database

### Scenario 4: API Endpoint
- [ ] Call `GET /api/auth/has-super-admin` with no super admin
- [ ] Should return: `{ ok: true, hasSuperAdmin: false }`
- [ ] Create super admin
- [ ] Call same endpoint
- [ ] Should return: `{ ok: true, hasSuperAdmin: true }`

### Scenario 5: Super Admin Can Still Approve Other Roles
- [ ] Create request for other roles (student, lecturer, system_analyst)
- [ ] As super admin, approve these requests
- [ ] All should succeed normally
- [ ] Verify role assignments are created

---

## BUG FIX #2: Password Visibility Toggle

### Scenario 1: Signup Form Password Toggle
- [ ] Navigate to signup form
- [ ] Scroll to password field
- [ ] Verify eye button appears on the right side of password input
- [ ] Type a password (e.g., "Test12345")
- [ ] Password should show as dots (●●●●●●●●●)
- [ ] Click eye button
- [ ] Password should become visible as text "Test12345"
- [ ] Click eye button again
- [ ] Password should be hidden again as dots
- [ ] Hover over eye button
- [ ] Button should change color to blue
- [ ] Click and hold eye button
- [ ] Button should slightly shrink (scale effect)

### Scenario 2: Signin Form Password Toggle
- [ ] Navigate to signin form
- [ ] Scroll to password field
- [ ] Verify eye button appears on the right side
- [ ] Type a password
- [ ] Verify visibility toggle works (same as Scenario 1)
- [ ] Test hover and click effects

### Scenario 3: Eye Button Accessibility
- [ ] Use keyboard Tab key to navigate to password field
- [ ] Continue tabbing to reach eye button
- [ ] Hover over button
- [ ] Should see tooltip: "Show password" or "Hide password"
- [ ] Press Enter to toggle visibility
- [ ] Password should toggle
- [ ] Screen reader should read: "Show password button" or "Hide password button"

### Scenario 4: Eye Button with Form Validation
- [ ] Enter invalid password (less than 8 chars)
- [ ] Eye button should still work
- [ ] Error message should show below field
- [ ] Eye button should not interfere with validation
- [ ] Toggle password and check error is still visible

### Scenario 5: Eye Button on Different Screen Sizes
- [ ] Test on desktop (1920px)
- [ ] Eye button should be visible and clickable
- [ ] Test on tablet (768px)
- [ ] Eye button should still be accessible
- [ ] Test on mobile (375px)
- [ ] Eye button should not cause layout issues
- [ ] Text input should be readable

### Scenario 6: Eye Button with Auto-filled Password
- [ ] (If browser has saved password)
- [ ] Let browser auto-fill password
- [ ] Eye button should still appear
- [ ] Toggle visibility should work with auto-filled password

---

## Cross-Browser Testing

### Chrome/Chromium
- [ ] Test both bug fixes
- [ ] Verify styling is correct
- [ ] Check console for errors

### Firefox
- [ ] Test both bug fixes
- [ ] Verify styling is correct
- [ ] Check console for errors

### Safari
- [ ] Test both bug fixes
- [ ] Verify styling is correct
- [ ] Check console for errors

### Edge
- [ ] Test both bug fixes
- [ ] Verify styling is correct
- [ ] Check console for errors

---

## Database Verification

### After Super Admin Restriction Implementation
- [ ] Query: `SELECT COUNT(*) FROM user_role_assignments WHERE role_name = 'super_admin' AND is_active = 1;`
- [ ] Should return: 1 (exactly one active super admin)

### After Creating Users with Other Roles
- [ ] Query: `SELECT * FROM user_role_assignments WHERE is_active = 1;`
- [ ] Verify multiple users can have student, lecturer, system_analyst roles
- [ ] Verify only one user has super_admin role

---

## Performance Testing

### Page Load Time
- [ ] Signup form should load in < 1 second
- [ ] Role dropdown fetch should be < 500ms
- [ ] No noticeable delay from super admin check

### API Response Time
- [ ] `GET /api/auth/has-super-admin` should respond in < 100ms
- [ ] `POST /api/auth/signup` should respond in < 500ms
- [ ] `POST /api/approvals/{id}/review` should respond in < 500ms

---

## Error Scenarios

### Network Errors
- [ ] Disable internet, reload signup
- [ ] Super admin check should fail gracefully
- [ ] All roles should be shown as fallback
- [ ] User can still submit signup

### Invalid Data
- [ ] Try to signup with super_admin when one exists (via API directly)
- [ ] Should return error with 409 status code
- [ ] Try to approve super_admin when one exists
- [ ] Should fail appropriately

### Edge Cases
- [ ] Multiple rapid clicks on eye button
- [ ] Should handle smoothly without errors
- [ ] Multiple rapid form submissions with super_admin role request
- [ ] Should not create duplicate requests

---

## Documentation Verification
- [ ] Read `/docs/changes-docs/2026-05-04-single-super-admin-restriction.md`
- [ ] Verify documentation matches implementation
- [ ] Read `/docs/changes-docs/2026-05-04-password-visibility-toggle.md`
- [ ] Verify documentation matches implementation
- [ ] Read `/docs/IMPLEMENTATION_SUMMARY.md`
- [ ] Verify all changes are documented

---

## Final Verification Checklist
- [ ] All features work as described
- [ ] No errors in browser console
- [ ] No errors in server logs
- [ ] Documentation is accurate
- [ ] Code follows project conventions
- [ ] No breaking changes to existing features
- [ ] Performance is acceptable
- [ ] Accessibility standards met
- [ ] Cross-browser compatibility confirmed

---

## Sign-Off

**Testing Date**: _______________

**Tested By**: _______________

**Status**: 
- [ ] All tests passed - Ready for production
- [ ] Some tests failed - Document issues
- [ ] Needs modifications - List required changes

**Notes/Issues Found**:
```
[List any issues here]
```

---

## Deployment Checklist

Once all tests pass:
- [ ] Code review completed
- [ ] Database migration applied
- [ ] Backend deployment complete
- [ ] Frontend deployment complete
- [ ] Production test of both features
- [ ] Monitor for errors in production
- [ ] User documentation updated (if needed)
