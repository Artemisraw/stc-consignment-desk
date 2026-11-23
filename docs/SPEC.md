# Sky Top Carriers product specification

## Purpose

An operations desk for the Nairobi–Mandera courier corridor. Staff register consignments, take payment, load available vehicles, advance trips through seven fixed branches, hand over arrived cargo, and answer public tracking enquiries.

## Roles

- `clerk`: create consignments, record payments, dispatch and advance trips, hand over cargo, at any branch. The counter form starts at the clerk's own branch.
- `branch_agent`: works only with cargo leaving from or going to their own branch:
  - books consignments at their own branch only, and corrects or cancels only what was booked there;
  - takes payments on cargo to or from their branch, and hands over only cargo addressed to it;
  - records a truck's arrival only when it reaches their branch; cannot load (dispatch) or cancel trips;
  - sees only their branch's consignments, the trips that start at or will reach their branch, and only their own cargo on those trips and manifests.
- `manager`: clerk actions plus fleet administration, payment reversals and reports.
- `owner`: unrestricted access, including staff accounts.

## Modules

1. Authentication and role-based authorization.
2. Fleet: vehicles, drivers, availability and maintenance.
3. Consignments: sender, receiver, route, line items, whole-KES charges, printable note.
4. Trips: manifest creation, dispatch and route-stop advancement.
5. Tracking: a public lookup and append-only event timeline.
6. Payments: cash, balance-on-delivery and manually confirmed M-Pesa references.
7. Dashboard and management reports.

## Business rules

- Money is stored as integer Kenyan shillings. Floating-point columns are prohibited.
- A consignment may be attached to only one active trip (`loading` or `in_transit`).
- Only `received` consignments can be loaded.
- A vehicle with an active trip is operationally `in_transit`, regardless of its stored fleet status.
- Each consignment status transition creates a tracking event in the same database transaction.
- Cargo changes to `arrived` at its destination branch and can only be delivered from that state.
- Handover records the outstanding amount as a balance-on-delivery payment before marking the consignment delivered.
- Route order is Nairobi, Wajir, Elwak, Wargadud, Lafey, Rhamu, Mandera.
- A trip runs one way along the route: `outbound` (towards Mandera) or `inbound` (towards Nairobi). It starts at the shared origin branch of its consignments, and every consignment on it must travel in the trip's direction.
- A driver may be on only one active trip, and only active drivers can be dispatched.
- Payments and handover lock the consignment row, so a double submission cannot overpay or hand over twice.

## Deferred phase-two work

Africa's Talking SMS, Daraja callback verification, offline PWA behavior and GPS telemetry are intentionally outside this MVP. The payment model and tracking metadata are designed to accept them later.
