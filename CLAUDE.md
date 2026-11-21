# Project instructions

- Stack: PHP 8.2+, Laravel 12, Vue 3, Inertia 2, Vite 6, MySQL in development, SQLite in tests.
- Use Form Requests for non-trivial input validation and policies for model authorization.
- Store KES as integer shillings. Never introduce floating-point money fields or calculations.
- Add new lifecycle values as backed PHP enums and cast them on the model.
- Every consignment status change and its `tracking_events` row belong in one database transaction.
- Preserve the one-active-trip rule in `TripService`; never implement trip loading directly in a controller.
- Keep controllers thin and operational workflows in services.
- Add feature coverage for every changed flow, especially trip loading, payments and handover.
- Never commit `.env`, credentials, production phone lists or Daraja secrets.
- The UI reference is `docs/prototype/stc-consignment-desk.html`; preserve its receipt-like visual language and strong mobile behavior.
