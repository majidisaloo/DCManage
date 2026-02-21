# Changelog

## [Unreleased]
- No pending entries.

## [0.1.12] - 2026-02-21
### Fixed
- Fixed dashboard/API parsing when WHMCS wraps addon output with admin HTML.
- Added explicit JSON markers in API responses and frontend fallback parser to reliably extract payloads.
- Restored update/version/cron/dashboard widgets visibility when previous API fetches showed `Invalid API response`.

## [0.1.11] - 2026-02-21
### Fixed
- Fixed dashboard API compatibility for servers running older PHP versions by replacing PHP 8-only syntax in API/service files.
- Fixed `Invalid API response` dashboard errors that blocked update/version/cron widgets and update buttons.
- Restored stable API JSON responses for:
  - dashboard health
  - dashboard version/update
  - dashboard cron status

## [0.1.10] - 2026-02-21
### Changed
- Rack visualization is now front-only (rear view removed) for cleaner operation flow.
- Unit selection UX improved: clicking a rack unit now targets that U directly in the unit configuration form.
- Datacenter action area was restyled with modern grouped action buttons.
- `Networks` tab and related Datacenter action were removed from admin navigation.

### Added
- Rack rename/update controls (rack name + total U) directly inside rack cards.
- Switch management now follows Datacenter pattern with collapsible `Add Switch` form.
- Switch vendor selector includes `Cisco`, `Nexus`, `MikroTik`.
- SNMP fields added to switch create/edit flow: management IP, version, port, community.
- SNMP connectivity test action per switch with visual status badge (`UP` green / `DOWN` red).
- Switch port and VLAN management block per switch:
  - list existing ports
  - add/update port metadata
  - delete ports
- Added DB migration/table `mod_dcmanage_switch_ports` for switch interface/VLAN inventory.

## [0.1.9] - 2026-02-21
### Changed
- Rack presentation was redesigned to a more realistic visual style with dedicated front/rear views.
- Rack cards now display a visual legend for server/switch/reserved/cable/airflow unit states.

### Added
- Rear-view rack units now include cable-lane indicators for occupied units to improve physical mapping clarity.
- Rack frame styling now includes rails, slot faces, and state-aware unit coloring for easier operational scanning.

## [0.1.8] - 2026-02-21
### Changed
- Settings field label was renamed from `Locale` to `Language` for clearer UX.
- Language selector now includes `Default` plus explicit `English` and `Persian` options.

### Fixed
- Default language mode now correctly follows active WHMCS admin language instead of forcing a stored override.
- Settings validation now restricts language values to `default`, `en`, or `fa` to prevent invalid state.

## [0.1.7] - 2026-02-21
### Changed
- Persian UI navigation now renders from the right side consistently in module tabs.
- PRTG tab was renamed to Monitoring, with provider selection designed for future extensions.
- Datacenter grid now uses tighter action layout and aligned table cells for clearer column placement.

### Added
- Monitoring provider selector now supports `PRTG`, `Cacti`, and `SolarWinds` options (with current implementation on PRTG).
- Datacenter rows now include operational actions: view Racks, Networks, Servers, Edit, and Delete.
- Rack view now includes U-level operational marking (`Reserved`, `Cable Mgmt`, `Airflow`, `Blank`) per unit.
- Added `mod_dcmanage_rack_units` migration for persistent rack unit annotations.

### Fixed
- Fixed dashboard API URL handling to prevent malformed endpoint calls.
- Fixed unhandled dashboard promise failures by hardening JSON response parsing and error handling.
- Fixed server listing under datacenter details to show rack name instead of raw rack ID.
- Fixed duplicate module heading/version rendering outside dashboard-focused view.

## [0.1.6] - 2026-02-21
### Changed
- Removed duplicate module heading/version line from non-dashboard pages.
- Datacenters page now uses an Add button + collapsed form instead of always-open create form.
- Datacenter list now includes rack visualization cards with U-level occupancy mapping.

### Added
- Added rack occupancy rendering that shows server and switch placement in each unit.
- Added optional U position fields for switch creation and rack placement tracking.
- Added migration step for switch U-position fields.

### Fixed
- Fixed language detection to honor saved locale and session/system language fallback.
- Fixed JS language mode detection by reading module shell language state directly.
- Removed `Code` dependency from datacenter creation form.

## [0.1.5] - 2026-02-21
### Changed
- UI direction is now language-aware: Persian renders RTL/right-aligned and English renders LTR/left-aligned.
- Tab order was standardized to start from Dashboard and end at Logs; Racks tab removed from primary menu.
- Dashboard KPI cards now include switches count and direct navigation to related sections.
- Updater source is fully hardcoded to the project GitHub repository/branch policy.

### Added
- Added Datacenter creation flow with automatic rack generation by count and per-rack unit size.
- Added Switch creation flow with dependent Datacenter -> Rack selection.
- Added Server creation flow with dependent Datacenter -> Rack selection and U position fields.
- Added Cron monitoring section with command list, status, last run, next run, and overall health state.
- Added Dashboard cron health widget (green/yellow/red logic), version status, latest release, and one-click update actions.
- Added package purchase log view in Logs tab.
- Added migration step for `rack_id` support on switches.

### Fixed
- Locale default now falls back to detected session/system language instead of static default.
- Added missing bilingual labels for newly introduced UI sections and actions.
- Improved input styling and form readability with modernized controls.

## [0.1.4] - 2026-02-21
### Changed
- GitHub update source is now hardcoded and no longer configurable by repo/branch fields.
- Dashboard now includes a dedicated version/update panel with current and latest release visibility.
- Admin module tabs now use a colored visual style for better navigation and readability.

### Added
- Added one-click update actions in dashboard: `Check Update` and `Apply Update`.
- Added auto-update toggle action directly in dashboard.
- Added `Settings` tab for global system options (timezone, locale, polling interval, cache TTL, retention, refresh period).
- Added update API endpoints for status check, apply, and auto-update toggle operations.

### Fixed
- Removed dependence on editable GitHub repository/branch values for update flow consistency.
- Improved update file copy routine to ignore non-essential artifacts like `.DS_Store`.

## [0.1.3] - 2026-02-21
### Changed
- GitHub release notes strategy moved from generic auto-generated changelog links to structured manual notes (`Changed / Added / Fixed`).
- Release pipeline now resolves a per-tag note body before publishing a release.

### Added
- Added versioned release note document support via `release-notes/<tag>.md`.
- Added fallback release note template when a dedicated note file is missing.

### Fixed
- Fixed release page readability for operators by replacing minimal `Full Changelog` output with explicit release summary sections.

## [0.1.2] - 2026-02-21
- Fixed release zip deployment layout for direct extraction in `public_html`:
  - release zip now contains `modules/addons/dcmanage` path.
  - unzip in WHMCS root (`public_html`) now places files in the expected module folder automatically.

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
