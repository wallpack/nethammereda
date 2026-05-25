# Skills Policy for lunch-mvp

This document defines how Codex should select skills for work in `lunch-mvp`.
Its purpose is to prevent competing visual directions from being combined in
the same task.

## 1. One Design Director Per Task

Every design task must have exactly one primary design/taste skill acting as
the design director.

Do not use several visual skills as equal authorities in one task. Supporting
skills may be used only for their explicitly limited role and must not
introduce a competing visual direction.

## 2. Catalog Redesign V2

For a full redesign of the existing `lunch-mvp` catalog, use only:

- Either `$taste-skill` or `$redesign-skill` as the single design director,
  never both in the same task.
- `$shadcn-vue` only for Vue, shadcn-vue, and Reka implementation patterns.
- `$fixing-accessibility` only during the final accessibility pass.

## 3. Forbidden Combinations For Catalog Redesign

When `$taste-skill` or `$redesign-skill` is directing a catalog redesign, do
not also use any of the following skills in that redesign task:

- `$frontend-design`
- `$interface-design`
- `$baseline-ui`
- `$make-interfaces-feel-better`
- `$emil-design-eng`
- `$impeccable`
- `$ui-ux-pro-max`

These skills may be useful individually in other tasks, but they must not be
combined with `$taste-skill` or `$redesign-skill` during one catalog redesign.

## 4. UI Audit Only

For a UI audit that does not change code, use only:

- `$ui-ux-pro-max`

An audit must not become an implementation or redesign pass unless the user
starts a separate task with explicitly permitted skills and file-change scope.

## 5. Final Micro-Polish

After the main redesign is complete, a narrowly scoped micro-polish task may
use:

- `$make-interfaces-feel-better`
- `$fixing-accessibility`

This phase may refine details and accessibility, but it must not perform a
layout redesign or change the selected visual direction.

## 6. Backend, Debug, And Testing Tasks

Do not use visual or design skills for backend, debugging, or testing tasks.
Use engineering skills appropriate to the task:

- `$debugging`
- `$code-reviewer`
- `$tdd`, if installed
- `$using-superpowers`, if installed

## 7. Prompt Rule

Every future Codex prompt for this project must explicitly state:

- Which skills may be used.
- Which skills must not be used.
- Whether frontend changes are permitted.
- Whether backend changes are permitted.
- Whether commits are permitted.

## 8. Recommended Prompt Line

Include this line in future Codex tasks:

```text
Read SKILLS_POLICY.md and follow it. Use only the skills explicitly allowed for this task.
```
