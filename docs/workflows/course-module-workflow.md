# LMS Course Module Workflow

## 1) Authority Rules (Based on Your Requirement)

- Only `system_analyst` can create course modules.
- Only `system_analyst` can appoint `students` and `lecturers` to a course module.
- `super_admin` does not do day-to-day course appointments, but can override in emergency/governance cases.
- `lecturer` and `student` cannot self-assign to a course module.

## 2) Core Entities

- `CourseModule` (module metadata)
- `CourseTermOffering` (module + term/semester instance)
- `ModuleLecturerAssignment`
- `ModuleStudentEnrollment`
- `AppointmentRequest`
- `AuditLog`

## 3) Course Module Lifecycle

1. `draft` - module being prepared
2. `review_ready` - ready for analyst validation
3. `published` - visible for appointments
4. `active` - currently running
5. `completed` - teaching period ended
6. `archived` - read-only historical state

## 4) End-to-End Workflow

### A) Create Course Module (System Analyst)

1. Analyst selects **Create Module**.
2. Enter required data:
   - `module_code` (unique)
   - `title`
   - `credit_value`
   - `department`
   - `learning_outcomes`
   - `assessment_scheme`
3. System validates uniqueness and required fields.
4. Save as `draft`.
5. Analyst reviews and marks `review_ready`.
6. Analyst publishes module -> `published`.

### B) Create Term Offering (System Analyst)

1. Select published module.
2. Create offering with:
   - `academic_year`
   - `semester`
   - `capacity`
   - `start_date`, `end_date`
3. Status set `active` when term starts.

### C) Appoint Lecturer to Module (System Analyst)

1. Analyst opens term offering.
2. Search eligible lecturer(s) by scope (department/faculty).
3. Assign lecturer with role:
   - `primary_lecturer` or `co_lecturer`
4. System checks conflict rules:
   - lecturer active,
   - no forbidden timetable overlap (if timetable enabled),
   - scope eligibility.
5. On success:
   - create `ModuleLecturerAssignment`,
   - notify lecturer,
   - audit event logged.

### D) Appoint Students to Module (System Analyst)

1. Analyst opens term offering.
2. Choose appointment method:
   - individual add, or
   - bulk add from approved student list.
3. System validates:
   - student account `active`,
   - prerequisite rules (if enabled),
   - capacity limit.
4. On success:
   - create `ModuleStudentEnrollment`,
   - notify student,
   - audit event logged.

### E) Reassignment / Removal

- Analyst can:
  - remove lecturer/student from module,
  - transfer to another lecturer/module section,
  - set effective date + reason.
- Historical records are retained; no hard delete of academic assignments.

### F) Completion and Archive

1. At term end, module offering moves to `completed`.
2. After grade finalization and retention checks, move to `archived`.
3. Archived offerings are read-only except super-admin governance actions.

## 5) Decision Rules

- If capacity reached -> appointment blocked; analyst may increase capacity or waitlist.
- If prerequisite fail -> appointment blocked with reason.
- If lecturer inactive -> assignment blocked.
- If module not `published` -> no appointments allowed.

## 6) Suggested Approval and Exception Flow

Normal flow:
- Analyst decision is final for student/lecturer appointments.

Exception flow:
- Policy breach/dispute/escalation -> `super_admin` review queue.
- Super admin can approve override or enforce rollback.

## 7) API Blueprint (Suggested)

- `POST /modules`
- `PATCH /modules/{moduleId}`
- `POST /modules/{moduleId}/publish`
- `POST /modules/{moduleId}/offerings`
- `POST /offerings/{offeringId}/lecturers`
- `DELETE /offerings/{offeringId}/lecturers/{lecturerId}`
- `POST /offerings/{offeringId}/students`
- `POST /offerings/{offeringId}/students/bulk`
- `DELETE /offerings/{offeringId}/students/{studentId}`
- `POST /offerings/{offeringId}/complete`
- `POST /offerings/{offeringId}/archive`

## 8) Required Audit Events

- `module_created`
- `module_published`
- `offering_created`
- `lecturer_assigned`
- `lecturer_unassigned`
- `student_enrolled`
- `student_unenrolled`
- `offering_completed`
- `offering_archived`
- `override_applied`

## 9) KPI / Operational Tracking

- Appointment turnaround time (analyst action time)
- % modules with assigned lecturer before term start
- Enrollment fill rate vs capacity
- Appointment rejection reasons trend

## 10) Non-Negotiable Constraints

- Module creation authority = `system_analyst`.
- Student/Lecturer module appointment authority = `system_analyst`.
- Super admin acts as governance and exception authority.
