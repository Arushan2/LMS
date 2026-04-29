# Copilot Instructions

These instructions apply to all AI-assisted changes in this repository.

## Change documentation

- Every code or behavior change must add a new doc in docs/changes-docs using this name pattern:
  - YYYY-MM-DD-short-description.md
- The doc must describe what changed, why, and how to verify it.
- If the change is purely cosmetic (docs, formatting only), still create a doc and note it as cosmetic.

## Workflow changes

- If a change impacts any core workflows described in docs/workflows, you must ask the user for confirmation before modifying those workflow docs.
- Only update workflow docs after the user explicitly approves.
- When updating, keep the workflow doc consistent with the actual behavior in code and UI.

## Style and quality

- Prefer small, focused edits and explain the reasoning in the change doc.
- Avoid committing secrets or default credentials to any doc or UI.
- Keep instructions and documentation in ASCII unless the target file already uses non-ASCII.
