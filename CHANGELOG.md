# Changelog

## [Unreleased]
- No pending entries.

## [0.1.22] - 2026-02-21
### Changed
- Replaced per-port `Delete` action with `Shut` / `No Shut` operational actions in Switches -> Ports table.
- Added configurable switch discovery interval in Settings (`Switch Discovery Interval (Minutes)`).

### Added
- Added automatic switch-port discovery cron task (`switch_discovery`) with lock protection and interval-aware execution.
- Added interface description (`ifAlias`) and interface index persistence for discovered switch ports.
- Added new cron monitor row and command hint for `switch_discovery`.

### Fixed
- Fixed VLAN detection by mapping `dot1qPvid` through `dot1dBasePortIfIndex` so VLAN values are resolved correctly on more switches.
- Improved discovery fallback path to keep VLAN/description detection working across different SNMP function availability.

## [0.1.21] - 2026-02-21
### Added
- Added SNMP compatibility layer for discovery:
  - `snmp2_real_walk`
  - `snmprealwalk`
  - fallback `snmp2_walk/snmpwalk`
- Added SNMP get compatibility fallback (`snmp2_get` -> `snmpget`) for connectivity checks.

### Fixed
- Fixed switch auto-discovery on hosts where `snmp2_real_walk` is missing but other SNMP functions are available.
- Improved discovery resilience by supporting both real-walk and list-walk PHP SNMP APIs.

## [0.1.20] - 2026-02-21
### Added
- Added robust switch port auto-discovery flow reuse helper to store discovered ports consistently.
- SNMP test action now auto-syncs/discovers switch ports when connectivity is successful.

### Fixed
- Fixed wrong-action form submits in Switches tab by prioritizing submit button action values (`dcmanage_action_btn`).
- Fixed `Port interface name is required` false error by auto-triggering discovery fallback when interface input is empty.
- Fixed Discover/Update/Delete port actions to use explicit button-bound action routing, even in wrapped WHMCS form contexts.

## [0.1.19] - 2026-02-21
### Changed
- Dashboard KPI cards were redesigned with vector icons, stronger color hierarchy, and cleaner card composition.
- Version panel was redesigned into a modern status board with aligned Current/Latest/Status metrics.
- Update controls were aligned into one responsive action row for cleaner UX.

### Added
- Added visual icon set for dashboard parameters (Datacenters, Racks, Switches, Servers, Ports, Breaches, Queue).
- Added explicit update state badge classes:
  - red for not updated
  - yellow for update available
  - green for updated
- Added semantic in-panel feedback styles for update actions (info/success/warning/danger).

### Fixed
- Fixed update status presentation consistency across Check/Apply/Auto-toggle actions.
- Improved dashboard typography and spacing to avoid stretched/flat appearance on wide screens.

## [0.1.18] - 2026-02-21
### Changed
- Switches tab UI was modernized with cleaner action buttons, improved visual hierarchy, and clearer ports panel layout.
- Port upsert form now uses explicit field labels (Interface/VLAN/Admin/Oper) to avoid RTL/LTR input confusion.

### Added
- Added `Discover Ports` action on each switch to auto-import interfaces/statuses via SNMP walk.
- Added status pill components for switch SNMP state and per-port Admin/Oper state.

### Fixed
- Fixed dashboard `Ports` counter to include both server ports and switch ports.
- Fixed interface name normalization for manual port save (`Ethernet 1/1` => `Ethernet1/1`).
- Replaced gray UP/DOWN badges with explicit green/red status pills for clearer online/offline visibility.

## [0.1.17] - 2026-02-21
### Fixed
- Hardened shell argument quoting in cron command generation to prevent fatal errors on hosts with restricted/disabled `escapeshellarg`.
- Added safe callable fallback path so Settings and Cron Monitor always render even on hardened PHP environments.

## [0.1.16] - 2026-02-21
### Fixed
- Fixed settings crash on hosts where `escapeshellarg()` is disabled in PHP `disable_functions`.
- Added internal shell-quote fallback to build cron command hints without relying on blocked PHP shell functions.
- Cron monitor/settings now open safely even in hardened hosting environments.

## [0.1.15] - 2026-02-21
### Changed
- Rack layout was redesigned to a compact card/tower UX instead of wide stretched rows.
- Rack unit rows now use fixed visual width, stronger hierarchy, and selectable interactive states.
- Rack editor controls are now grouped in modern side panels inside each rack card.

### Fixed
- Datacenter rack view responsiveness was improved for desktop and mobile breakpoints.
- Resolved oversized horizontal rack rendering that made the page visually unbalanced.

## [0.1.14] - 2026-02-21
### Changed
- Removed `Automation / Cron` tab from main navigation; cron monitoring remains consolidated under `Settings`.
- Queue KPI card now routes to `Settings` instead of removed automation tab.

### Fixed
- Cron commands in settings now use auto-detected real server paths (no `/path/to/whmcs` placeholder).
- PHP binary path in cron command hints is auto-detected from available CLI binaries.

## [0.1.13] - 2026-02-21
### Changed
- Dashboard KPI cards were redesigned with cleaner card hierarchy, centered values, and stronger visual emphasis.
- Version/Update card and actions were restyled to a more modern, card-based UI.
- Dashboard table headers and card surfaces were refined for improved readability.

### Fixed
- All module alerts/notifications now render centered text with consistent spacing and modern visual style.

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
