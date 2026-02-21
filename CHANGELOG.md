# Changelog

## [0.1.1] - 2026-02-21
- Fixed GitHub release package format:
  - zip name no longer contains `public_html`.
  - zip root contains only `dcmanage/` module folder.
  - no README/CHANGELOG files are included in release zip payload.

## [0.1.0] - 2026-02-21
- Added initial WHMCS addon skeleton for `DCManage`.
- Added versioned database migrations for datacenters, racks, networks, switches, servers, ports, iLO, PRTG, scope, service linking, usage, packages, purchases, graph cache, logs, locks, jobs.
- Added API-first internal router with JSON endpoints for dashboard health, datacenters, traffic list, and graph cache fetch.
- Added cron runner entrypoint with tasks: `poll_usage`, `enforce_queue`, `graph_warm`, `cleanup`.
- Added usage cycle resolver with next due date alignment and reset behavior.
- Added lock manager and DB job queue (idempotent-ready foundation).
- Added PRTG adapter (cURL-based, no `shell_exec`) with sensor value and historic data methods.
- Added Bootstrap 4 responsive admin shell and Chart.js integration placeholder.
- Added bilingual UI support (`fa`/`en`) with auto language detection from WHMCS session.
- Added GitHub release packaging workflow for `public_html` unzip deployment.
- Added self-update cron task (`self_update`) to fetch and apply latest GitHub release automatically.
