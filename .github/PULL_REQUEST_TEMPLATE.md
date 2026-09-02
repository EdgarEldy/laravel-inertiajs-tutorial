## Branch

<!-- e.g. feature/core-architecture -->

## Task checklist

<!-- Copy the relevant section's checklist from README.md and check off each item. -->

- [ ]

## Commit summary

<!-- One line per commit, summarizing what changed and why. -->

## Test checklist

- [ ] Feature tests pass
- [ ] Browser tests pass (Pest v4, where the branch's checklist calls for them)
- [ ] `php artisan test` is green against the real MySQL test database (never sqlite/in-memory)
- [ ] `vendor/bin/pint --test` passes
- [ ] `npm run build` succeeds

## Code review checklist

- [ ] No business logic in controllers - validate via a Form Request, call exactly one Service method, redirect
- [ ] Every write route is protected with `->middleware('can:RESOURCE:ACTION')`
- [ ] Assignments only flow permissions -> role, roles -> user, never the reverse
- [ ] `/roles/{role}/permissions` stays a separate page/route from `/roles/{role}/edit`
- [ ] Every RBAC mutation calls `AuditLogger` (via `LogsAuditEvents`)
- [ ] The last-admin lockout check is tested in both directions where relevant
- [ ] Every `*Service::delete*()` rejects when the entity is still referenced elsewhere
- [ ] Every Inertia prop backed by a model/collection goes through a `JsonResource`
- [ ] Every list-page relationship is eager-loaded explicitly (`with(...)`)
- [ ] Commits are atomic (one commit per file, tightly coupled pairs may share), follow Conventional Commits, no em dash, no other-project references
