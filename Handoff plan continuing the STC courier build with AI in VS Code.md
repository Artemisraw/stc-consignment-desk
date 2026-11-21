# Handoff plan: continuing the STC courier build with AI in VS Code

The prototype is the visual spec. The work now is giving the AI enough context that it builds the real thing without guessing.

## 1. Set up the project (about 30 min)

- Create the Laravel project and Git repo, with a `main` branch and a `dev` branch.
- Set up MySQL locally. Install Vue with Inertia (or a separate Vue SPA).
- Install an AI coding tool in VS Code, such as Claude Code or a Copilot-style extension. Check its docs for current install steps.
- Commit the empty scaffold first so you can diff everything the AI changes.

## 2. Give the AI context

AI tools perform far better with written context than with one-line prompts. Add these to the repo:

| File | What goes in it |
| --- | --- |
| `docs/SPEC.md` | The framework: modules, statuses, rules (a consignment can be on one active trip only), roles |
| `docs/schema.md` | The simplified data model (branches, vehicles, drivers, customers, consignments, items, trips, trip\_consignments, tracking\_events, payments) |
| `docs/prototype/stc-consignment-desk.html` | The prototype file, as the UI and flow reference |
| `docs/samples/` | Photos of the consignment note and manifest, since they define the printable layouts |
| `CLAUDE.md` (or your tool's equivalent) | Stack, conventions, and rules, as below |

**Conventions worth writing down:**

- Laravel 11, Form Requests for validation, Eloquent policies per role
- Money stored as integers (KES), never floats
- Status values as PHP enums
- Every status change writes a `tracking_events` row
- Feature tests for each flow, and small commits

## 3. Build in thin slices

Ask the AI for one slice at a time, review the diff, run the tests, and commit.

1. **Foundation:** auth, roles (clerk, branch agent, manager, owner), branches seeder with the 7 stops
2. **Fleet:** vehicles and drivers CRUD, with status derived from active trips
3. **Consignments:** intake form, tracking number generator, printable note
4. **Trips:** create a trip, load consignments, enforce the one-active-trip rule, advance stops
5. **Tracking:** public lookup page and event timeline
6. **Payments:** cash and balance-on-delivery first, then M-Pesa
7. **Dashboard and reports**
8. **Phase 2:** SMS (Africa's Talking), offline PWA, GPS

## 4. How to prompt it

- Reference the files: "Implement step 3 from `docs/SPEC.md` using the schema in `docs/schema.md`. Match the layout in the prototype."
- Ask for the plan first, then the code: "List the files you'll create or change before writing anything."
- Require tests: "Include feature tests for the one-active-trip rule."
- Keep scope tight. One module per session avoids sprawling changes.

## 5. Guardrails

- Never commit `.env` or API keys. Use Daraja sandbox credentials until go-live.
- Review anything touching money (totals, balances, payment callbacks) line by line.
- Seed realistic data with factories, so you can test with the same volume as their ledgers.

## Next steps

1. Create the repo and scaffold, then drop in the prototype and the sample photos.
2. Write `docs/SPEC.md`, `docs/schema.md` and `CLAUDE.md`, ready to paste into the repo.
3. Draft the first prompt for the foundation and fleet slices.
