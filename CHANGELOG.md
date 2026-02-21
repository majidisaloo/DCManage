# Changelog

## [1.1.0] - 2026-02-21
- Added bilingual UI support (`fa`/`en`) with automatic language resolution from WHMCS user/admin session.
- Added translation dictionary helper and localized module tab labels/messages.
- Updated addon author metadata to `MAJID ISALOO`.
- Kept API-first architecture and existing cron/queue behavior unchanged.

## [1.0.0] - 2026-02-21
- Added initial WHMCS addon skeleton for `DCManage`.
- Added versioned database migrations for datacenters, racks, networks, switches, servers, ports, iLO, PRTG, scope, service linking, usage, packages, purchases, graph cache, logs, locks, jobs.
- Added API-first internal router with JSON endpoints for dashboard health, datacenters, traffic list, and graph cache fetch.
- Added cron runner entrypoint with tasks: `poll_usage`, `enforce_queue`, `graph_warm`, `cleanup`.
- Added usage cycle resolver with next due date alignment and reset behavior.
- Added lock manager and DB job queue (idempotent-ready foundation).
- Added PRTG adapter (cURL-based, no `shell_exec`) with sensor value and historic data methods.
- Added Bootstrap 4 responsive admin shell and Chart.js integration placeholder.
- Added release documentation and safe update strategy notes.
