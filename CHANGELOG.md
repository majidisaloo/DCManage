# Changelog

## [Unreleased]
- No pending entries.

## [0.1.57] - 2026-02-22
### Changed
- Search normalization improved for live tables so Persian/Arabic digits are matched the same as English digits.
- Server list pagination now follows the same UX as switch ports: controls are below table, default page size is `15`, and page size is selectable (`10/15/25/50/100`).

### Fixed
- Server add form now enforces datacenter-first dependency: `Rack`, `Switch`, and `Switch Port` are disabled until datacenter is selected.
- Rack/Switch selection is now properly scoped by selected datacenter before loading switch ports.

## [0.1.56] - 2026-02-22
### Changed
- Switch Ports table now uses fixed column layout so all rows stay vertically aligned even with long description text.
- Switch Ports pagination controls were moved below the table.
- Added per-page selector for Switch Ports pagination with default `15` (`10/15/25/50/100`).

## [0.1.55] - 2026-02-22
### Fixed
- Fixed VLAN detection for SVI-style interfaces (`VlanX`, `vlanifX`) so VLAN column no longer stays empty when PVID mapping is unavailable.
- Added fallback VLAN extraction from interface name/description during switch port discovery and single-port check.
- Added storage-time VLAN fallback to persist VLAN value even when SNMP PVID OIDs are missing on specific devices.

## [0.1.54] - 2026-02-22
### Changed
- Top navigation tabs now include compact vector icons beside labels for clearer visual scanning.
- Dashboard KPI area is now more graphical with a vector hero band and decorative per-card watermark icons.
- Improved tab and KPI visual hierarchy for better balance on both desktop and mobile breakpoints.

## [0.1.53] - 2026-02-22
### Fixed
- Fixed persistent stale update warning banner (`Canceled before HTTP request`) after successful/no-active update by normalizing idle update runtime state.
- Fixed Cron Health status logic to mark tasks `OK` based on successful task run age, not only strict `completed` message text.
- Fixed mismatch between Dashboard and Settings cron status output for legacy `... executed/processed` log messages.
- Fixed Settings action button spacing in iLO proxy block (`Save Settings` / `Test iLO Proxy`) with consistent form action layout.

## [0.1.52] - 2026-02-22
### Fixed
- Fixed Cron Health false-fail state in both Dashboard and Settings monitor by accepting both dispatcher-style and legacy task success log messages.
- Cron status now correctly marks tasks as healthy when logs contain `poll_usage processed`, `... executed`, or `task:<name> completed`.

## [0.1.51] - 2026-02-22
### Fixed
- Fixed dashboard update API instability (`dashboard/version`) by adding safe fallback behavior when GitHub release check fails.
- Fixed stuck cancel/update state by cleaning stale update jobs and clearing orphaned cancel flags when no active update job exists.
- Fixed queue KPI navigation on dashboard to open a dedicated Queue tab instead of Settings.
- Fixed update runtime responsiveness by reducing updater retry/timeout overhead and running one immediate queue batch on apply.

### Added
- Added `Queue` tab with filter/search/pagination for `mod_dcmanage_jobs`.
- Added queue actions: cancel pending/running job, retry failed/canceled job, and clear completed history.
- Added latest-release metadata cache for resilient version status display during temporary remote/API failures.

### Changed
- Increased dashboard KPI icon sizes and label proportions for better visual balance on large screens.

## [0.1.50] - 2026-02-21
### Fixed
- Fixed cron health false-fail in dispatcher mode by writing per-task completion logs (`task:<name> completed`) when dispatcher executes tasks.
- Dispatcher now isolates task failures (logs failure per task and continues with remaining tasks) instead of aborting the full run.

## [0.1.49] - 2026-02-21
### Added
- Logs page now has search, level/source filters, level/date sorting, and clear actions (system/purchase/all).
- Added pagination for system logs and purchase logs.
- Added live search + pagination for Servers table.
- Added live search + pagination for Switch Ports table.

### Fixed
- Improved VLAN parsing for hex-formatted SNMP values.
- Added bridge-port fallback VLAN resolution for single-port SNMP check.

## [0.1.48] - 2026-02-21
### Fixed
- Fixed inconsistent padding/margin in settings and form cards.
- Added consistent spacing between labels, inputs, and action buttons.
- Added dedicated form action area spacing so buttons no longer stick to input rows.

## [0.1.47] - 2026-02-21
### Changed
- Updater switched to queue-based flow to prevent overlapping update tasks and false concurrent runs.
- Dashboard update actions now support runtime status polling and explicit cancel request.

### Added
- Added update runtime endpoints: `update/status` and `update/cancel`.
- Added release package validation before apply (required files, package version, content-length check).

### Fixed
- Fixed false-positive update logs by logging `updated` only after file copy + installed version validation.
- Improved updater reliability with longer timeouts and retry logic for GitHub requests.

## [0.1.46] - 2026-02-21
### Changed
- Cron configuration is now single-entry: one cron command (`dispatcher`) handles all module tasks by internal schedule.
- Settings Cron Monitor now shows one install command and keeps per-task health without requiring separate cron lines.

### Added
- Added dispatcher mode in `cron.php` and made it the default behavior when no task argument is provided.

## [0.1.45] - 2026-02-21
### Changed
- Standalone iLO top tab removed; iLO management is now inside Servers workflow.
- Added iLO fields directly into server create/edit forms.

### Added
- Added server delete action with cleanup of linked ports and sensor mappings.
- Added iLO proxy settings in Settings (`http`/`https`/`socks5`) with connection test.

### Fixed
- Fixed server edit panel JavaScript parse failure (`Invalid escape in identifier`) caused by malformed inline script escaping.
- Server edit/details now opens and works correctly after JS fix.

## [0.1.44] - 2026-02-21
### Added
- Added live search input in each switch ports panel.
- Search now filters by `Interface`, `Description`, and `VLAN` in real-time.

### Fixed
- Improved port lookup workflow for large switch interface lists by enabling immediate in-table filtering.

## [0.1.43] - 2026-02-21
### Changed
- Port speed normalization now handles oversized/non-standard SNMP raw values.
- Speed labels now render compact G-units with decimal support when needed.

### Added
- Added heuristic snap-to-common-Ethernet-speeds logic for clearer operational speed display.

### Fixed
- Fixed abnormal speed outputs like `2140046M` by normalizing scale and mapping to realistic speeds.

## [0.1.42] - 2026-02-21
### Changed
- Server create dependency flow is now strict: Datacenter must be selected before rack/switch become usable.
- Switch-port loading now requires both Datacenter and Switch selection.

### Added
- Added explicit disable/reset behavior for rack/switch/port fields when datacenter is empty.

### Fixed
- Fixed rack options appearing/selectable before selecting datacenter.
- Fixed switch port list loading reliability by enforcing guarded DC+Switch fetch flow.

## [0.1.41] - 2026-02-21
### Changed
- Increased global spacing between top tabs and content area.
- Standardized tabs-to-content vertical rhythm across module pages.

### Fixed
- Fixed visual sticking of top menus to the section/table below in pages like Servers.

## [0.1.40] - 2026-02-21
### Changed
- Port admin status label updated from `Shut/No Shut` to `Active/Suspended`.
- Port action buttons renamed to `Activate` / `Suspend` for operational clarity.

### Added
- Added per-port `Check Port` action to run immediate SNMP check for only the selected interface and refresh its row data.

### Fixed
- Expanded absent-state parsing to detect additional raw variations such as `abcent` and platform-specific absent text patterns.
- `SFP/OSPF absent` style raw status values now map correctly to absent state badge in port link status.

## [0.1.39] - 2026-02-21
### Changed
- Removed manual `Add/Update Port` block from `Switches -> Ports`.
- Port management is now discover/sync-first instead of manual entry.

### Fixed
- Prevented manual uplink/interface input confusion and wrong source data entry.

## [0.1.38] - 2026-02-21
### Changed
- Bulk server creation form no longer requests rack selection.
- Bulk provisioning is now datacenter-level only for safer high-volume pre-create workflow.

### Fixed
- Prevented rack over-assignment when creating large hostname ranges (example: 100+ servers).
- Bulk-created servers now always save with empty rack mapping (`rack_id = null`) for later manual placement.

## [0.1.37] - 2026-02-21
### Fixed
- Fixed crowded spacing between bulk server example hint and `Create Range` button.
- Added explicit block spacing so hint text and submit action render with consistent vertical rhythm.

## [0.1.36] - 2026-02-21
### Changed
- Refined dashboard update control spacing and checkbox alignment for cleaner visual rhythm.
- Auto Update checkbox label now has proper baseline and breathing space.

### Added
- Update status message area now appears only when an action result exists (check/apply/toggle error), and stays hidden by default.

### Fixed
- Removed persistent empty rounded message box under update buttons.
- Fixed crowded `Auto Update` checkbox/text rendering seen on some screens.

## [0.1.35] - 2026-02-21
### Changed
- Servers create flow is now dependency-first: `Datacenter -> Rack -> Switch -> Switch Port`.
- Add Server form no longer preloads all switch ports; ports are loaded only for the selected switch.
- Port selector labels now include interface name, interface description, and link state (`Connected / Not Connected / SFP Absent`).

### Added
- New internal API endpoint `switch/ports` for datacenter-safe, switch-scoped port listing.
- New per-server inline mapping editor in Servers list (post-create) for switch/port and traffic sensor mapping.

### Fixed
- Prevented cross-datacenter switch assignment while updating server mappings.
- Reduced create-form over-selection risk by moving traffic sensor/PRTG mapping to server-level edit workflow.

## [0.1.34] - 2026-02-21
### Fixed
- Removed button-to-border crowding by enforcing a global top margin for section table wrappers.
- Added explicit bottom spacing under top toolbars (including Datacenters top action row) so action buttons no longer stick to the next section line.

### Changed
- Unified vertical rhythm between action rows and table blocks across all implemented pages.

## [0.1.33] - 2026-02-21
### Changed
- Applied global spacing normalization across module pages for consistent vertical and horizontal rhythm.
- Unified action/button presentation and checkbox styling in dashboard update controls.
- Added unified table wrapper style to Settings, Monitoring, Switches, Servers, and Logs to prevent controls sticking to border lines.

### Fixed
- Fixed line-to-button crowding issue visible near top section borders across tabs.
- Improved responsive behavior on large monitors and mobile by adding dedicated wide-screen and small-screen layout tuning.

## [0.1.32] - 2026-02-21
### Changed
- Removed duplicate in-page tab headings from implemented pages so the active tab title appears only once in top navigation.
- Updated `Switches` page header row to right-aligned action-only layout (`Add Switch` button) without repeated section title.

### Fixed
- Resolved repeated `Switches/Servers/Monitoring` labels that appeared both in tab and page content.

## [0.1.31] - 2026-02-21
### Changed
- Replaced technical `Admin/Oper` wording in switch ports with user-facing labels:
  - `Shut / No Shut` (port config state)
  - `Link Status` (connected state)
- Updated manual port status selectors to the same operational labels.

### Added
- Added explicit port link state `SFP Absent` for interfaces where transceiver/module is not present.

### Fixed
- SNMP oper-status parsing now distinguishes `absent/notPresent` from normal down state, so missing module ports are shown correctly.

## [0.1.30] - 2026-02-21
### Changed
- Refined dashboard spacing in Version/Cron panels for cleaner and consistent padding.
- Improved action row alignment for update controls (Auto Update + buttons) across desktop and mobile.
- Cron header now uses a dedicated responsive layout for correct spacing of title and status badge.

### Fixed
- Removed the empty update message placeholder gap by hiding the message box until text is present.
- Fixed cramped mobile spacing where update controls and status blocks appeared stuck to borders.

## [0.1.29] - 2026-02-21
### Added
- Added `Bulk Create Servers` form in Servers tab to create hostname ranges (example: `MDP-301` to `MDP-399`).
- Bulk form supports Datacenter/Rack selection, default `U Height`, and default notes for all created servers.

### Changed
- Server form JavaScript now also filters rack options for the bulk datacenter selector.

### Fixed
- Bulk create skips existing hostnames and returns a clear created/skipped summary after execution.

## [0.1.28] - 2026-02-21
### Added
- Server create flow now supports selecting switch and switch port directly.
- Added PRTG sensor multi-select on server form (multiple sensors per server for traffic).
- Added API endpoint `prtg/sensors` for loading sensor lists from selected PRTG instance.
- Added PRTG instance CRUD in Monitoring tab (add/list/test/delete).

### Changed
- Servers table now shows linked `Switch/Port` and traffic sensor count/preview.

### Fixed
- Server provisioning workflow now saves switch/port and sensor mappings in database instead of hostname-only create.

## [0.1.27] - 2026-02-21
### Changed
- Datacenters section spacing was normalized on all sides.
- Added consistent inner padding between section borders and content.

### Fixed
- Fixed cramped alignment where action button and table appeared too close to border lines.

## [0.1.26] - 2026-02-21
### Changed
- Increased top spacing in Datacenters action bar.
- `Add Datacenter` button now has clearer breathing room from the top border line.

## [0.1.25] - 2026-02-21
### Changed
- Removed duplicate in-page `Datacenters` title under the top tabs.
- Datacenters header row now shows only the `Add Datacenter` button.

### Fixed
- Resolved visual duplication where Datacenters label appeared both as active tab and as page heading.

## [0.1.24] - 2026-02-21
### Changed
- Removed `Ports` tab from top navigation to avoid duplicate workflow with Switches.
- Dashboard KPI cards no longer render a standalone `Ports` card.

### Fixed
- Legacy links using `&tab=ports` now redirect to `Switches` tab to preserve backward compatibility.

## [0.1.23] - 2026-02-21
### Added
- Added switch port speed visibility in Switches -> Ports table.
- Discovery now collects and stores:
  - `speed_mbps` from `ifHighSpeed` (fallback `ifSpeed`)
  - `speed_mode` from MAU auto-negotiation status

### Changed
- Port speed now renders with operational mode labels:
  - `1G`, `10G`
  - `Auto 1G`, `Auto 10G`

### Fixed
- Improved SNMP port profiling so speed and auto-negotiation status are recognized instead of remaining unknown.

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
