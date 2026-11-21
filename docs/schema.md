# Data model

| Table | Purpose | Important fields |
| --- | --- | --- |
| `users` | Staff identity and access | `role`, `branch_id` |
| `branches` | Ordered route stops | `name`, `route_order` |
| `drivers` | Fleet drivers | `licence_number`, `is_active` |
| `vehicles` | Trucks and maintenance state | `registration_number`, `status`, `default_driver_id` |
| `customers` | Sender and receiver contacts | `name`, `phone`, `email` |
| `consignments` | Shipment header and financial total | `tracking_number`, branch/customer FKs, `status`, integer `total_amount`, integer `paid_amount` |
| `consignment_items` | Printable note lines | `quantity`, `description`, integer `amount` |
| `trips` | A vehicle journey | vehicle/driver/current-branch FKs, `status`, timestamps |
| `trip_consignments` | Trip manifest | unique trip/consignment pair, `unloaded_at` |
| `tracking_events` | Append-only public history | branch, status, description, metadata, `occurred_at` |
| `payments` | Completed or pending collections | integer `amount`, method, status, reference |

Foreign keys protect operational links. Customer and user references that represent historical actors are nullable on deletion; cargo, items, events and payments cascade only when their owning consignment is removed.
