# Changelog

## [0.1.100] - 2024-05-31
### Fixed
- Hotfix: Removed undefined `notes` dependency in `mod_dcmanage_switch_ports` during migrationV18 that halted the upgrade process.

## [0.1.99] - 2024-05-31
### Added
- Implemented Port Locking feature with UI toggle and backend enforcement.
- Added Edit functionality for Datacenter PRTG Mappings.
- Schema Migration V19 for fixing missing `ilo_host` column in mod_dcmanage_servers.

### Changed
- Increased column spacing in Switch Ports table for better readability.
- Improved UI parity between Server Edit PRTG mapping and Datacenter PRTG mapping forms.

### Fixed
- Fixed Traffic Graph 400 Bad Request error by passing `server_id` instead of `service_id`.

## [Unreleased]

## [0.1.98] - 2024-05-30
### Added
- Schema Migration V17
- Added Subgroup 2 to PRTG Group mapping UI for nested monitoring.
- Added native WHMCS iframe proxy view for iLO HTML5 Consoles.

### Changed
- Converted the Traffic Graph Date picker to a robust, inline single row using modern `datetime-local` elements.
- Optimized Table Layouts globally by dropping restrictive fixed CSS column widths.

### Fixed
- Fixed bug preventing PRTG Sensors deep in subgroups from returning in Server Edit searches by adopting `filter_name` / `filter_objid`.
- Fixed Traffic Graph fatal issue where the date parsing output invalid characters that crashed the PRTG API query.
- Ensured JS Pagination properly hides unselected table rows by removing overlapping `thead tr` CSS styles.

## [v0.1.97] - 2024-05-30
### Fixed
- Fixed PRTG sensor drop-downs inside the Server Edit modal showing "ID" instead of "Name" for previously-saved sensor IDs
- Upgraded the Switch Ports and Servers table JS pagers to show interactive `[1] [2] [3]` windowed page numbers instead of bare `1/X`
- Reverted the card bottom margin for KPI dashboard cards to improve spacing on larger screens
- Fixed table headers disappearing on pagination pages 2+

## [v0.1.96] - 2024-05-29
### Fixed
- Fixed fatal error `Call to undefined function dcmanage_log()` in `dcmanage.php` that blocked the Servers tab rendering.
- Fixed dashboard KPI cards vertical spacing to avoid collapse/sticking between rows on smaller viewports.
- Enhanced table responsiveness (`width: 100%; table-layout: fixed;` with word-wrap) to prevent wide fields like "Free space" / "Descriptions" from causing horizontal overflow.
- Prevented Datatables from hiding `thead` row headers on pages 2+ by enforcing `display: table-header-group !important`.
- Restyled global `Select2` dropdown elements with modern UI metrics (rounded borders, generous padding, focus rings) to fix the legacy WHMCS UI dropdown appearance.
- Delayed the initial Cron Health dashboard API request by 250ms to ensure the visual KPI cards render and layout first without jumping.

## [0.1.95] - 2026-02-25
### Added
- Replaced multiple PRTG selection dropdowns (Group, Subgroup, Device, Sensor) in Server Edit -> Monitoring with single Select2 AJAX search for exact sensor.
- Applied searchable Select2 interface to 1st-level Traffic switch/port mapping.
- Added "Used / Total" visual readout for Server View traffic cards (sums Download/Upload + maps Datacenter base/extra quota).

### Fixed
- Fixed ReferenceError `Can't find variable: buttons` blocking Traffic Graph custom date selections.
- Re-architected UsageEngine polling to strictly respect the new `start_date` bounds (prevents fetching historical usage prior to server activation).
- Enforced target host/IP selection inside monitoring Network Discovery to prefer `dedicatedip` over `hostname`.
- Stabilized dynamic Form Action alignments/padding within Server Edit overlays.
- Standardized all generated 'Remove' action buttons to Bootstrap Danger styling (`btn-danger`) and auto-hide logic for initial single rows.

## [0.1.85] - 2026-02-23
### Fixed
- Forced horizontal flex layout for action buttons row (flex-direction row, nowrap, inline-flex forms).

## [0.1.84] - 2026-02-23
### Fixed
- All 5 action buttons (Apply Filter, Reset, Clear System, Clear Purchase, Clear All) now in one row below filter fields.
- Fixed Clear All Logs confirm dialog (moved from onsubmit to onclick for reliability).

## [0.1.83] - 2026-02-23
### Fixed
- Moved Apply Filter and Reset buttons into the Date row so they no longer appear below the Sort dropdown.
- Reordered filter fields: Sort → Source → Level → Search.

## [0.1.82] - 2026-02-23
### Fixed
- Improved button padding and spacing on Logs page (filter actions and clear-log buttons).

## [0.1.81] - 2026-02-23
### Fixed
- **Updater: OPcache invalidation** — After copying files, all `.php` files are now invalidated via `opcache_invalidate()` + `opcache_reset()`. This fixes the root cause of partial updates where PHP served stale compiled bytecode.
- **Updater: Per-file logging** — `copyRecursive` now counts and logs total files copied for debugging.
- Expanded friendly log message map (11 additional translations for dispatcher, switch discovery, poll_usage).
- Badge colors on Logs page now use inline styles for guaranteed visibility in WHMCS theme.

### Changed
- Release workflow now builds and attaches a clean `DCManage-<tag>.zip` artifact to GitHub Releases.

## [0.1.80] - 2026-02-23
### Added
- Traffic page: Download/Upload columns with per-column sorting (download, upload, total).
- Server View: Traffic graph (Chart.js) with 2h/2d/7d/30d/1Y range selector.
- Server View: Exact numeric Download/Upload/Total readouts in both Bytes and GB.
- Server View: iLO power buttons with solid Bootstrap colors and Font Awesome icons.
- Logs page: Date range filter (Date From / Date To).
- Logs page: Friendly human-readable message names replacing internal task identifiers.
- 8 new I18n keys for both English and Persian (server_ilo_test, server_basic_info, etc.).

### Fixed
- Fixed JS syntax error in admin.js (stray closing brace broke outer IIFE closure).
- Fixed broken I18n key reference (`monitoring_map_notes_placeholder` → `monitoring_notes`).

### Changed
- Standardized all remaining modals (Datacenter Add, Monitoring Add, Bulk Server) to `modal-xl`.
- Server Edit: iLO test button styled as `btn-warning` with vial icon; failures show `alert-danger`.
- Server Edit: iLO test success shows `alert-success` instead of plain text.

## [0.1.79] - 2026-02-23
### Fixed
- Fixed Datacenters Rack/Units styling constraint issues where elements were stretched too tall vertically.
- Improved fallback VLAN extraction logic on the Switches module when PRTG SNMP values have unexpected characters.
- Fixed the layout paddings and spacings on the generic main datatable wrappers.
- Redesigned Monitoring View and Edit modes to display inline via cards, fixing WHMCS backdrop `modal-xl` z-index freezing UI lockups.
- Enforced safe left-to-right (LTR) cascading flow for Monitoring element assignment configurations regardless of RTL portal display.
- Relocated PRTG "Test Connection" alerts securely onto the active monitoring canvas instead of absolute page top.

## [0.1.78] - 2026-02-23
### Added
- Added `.github/workflows/release.yml` for automated GitHub Releases via GitHub Actions.
- Release body notes are now automatically generated by parsing `CHANGELOG.md` for the pushed version tag.

## [0.1.77] - 2026-02-23
### Added
- Added `ilo/action` API endpoint to handle Server Redfish power actions (On, Off, Graceful Restart, Force Restart).
- Added POST request body support to `iloCurlJson` helper for Redfish interactions.
- Added visual loading indicators (spinners) and timeouts to all dependent selector fetch requests to prevent UI hangs.

### Changed
- Expanded the Server Add/Edit modals to modal-xl for better width and responsiveness.
- Redesigned Server View modal into a 4-card structure (Basic Info, Network Ports, Control Port, Discovery/Monitoring) for improved readability.
- Replaced internal keys (like `power_status`) with human-friendly translated text labels.
- Replaced `-` placeholders with typographically correct em-dashes `—`.
- Enforced left-to-right order dependency for Monitoring Mapping dropdowns (Probe -> Group -> Subgroup -> Device).
- Improved PRTG list displays to show item Names alongside IDs.

### Fixed
- Fixed pagination layout and padding issues on the Switches module interface.
- Fixed search insensitivity for both Persian and English numeric digits across all module search boxes.
- Fixed modals not auto-closing after a successful save operation.

### Removed
- Removed the legacy internal 'Traffic Management' PHP pages/logic entirely and replaced them with a placeholder pending a full redesign.

## [0.1.76] - 2026-02-23
### Fixed
- Fixed Server View/Edit blank/black screen by wrapping modal auto-show script in `DOMContentLoaded` for reliable timing.
- Fixed Monitoring View/Edit blank/black screen by adding non-jQuery fallback to edit modal auto-show and wrapping view/edit scripts in `DOMContentLoaded`.
- Fixed Monitoring edit modal (early return path) missing non-jQuery display fallback, causing blank screen when Bootstrap modal plugin unavailable.
- Fixed non-PRTG monitoring view (SolarWinds/Cacti) returning nearly blank page — now shows type-specific detail cards with URL, user, and back navigation.
- Improved VLAN detection fallback to handle trunk/tagged sub-interface dot notation (e.g. `Eth1/1.100`, `ge-0/0/1.200`).
- Fixed KPI card text/value bleeding into rounded corners by adding `overflow: hidden` and `text-overflow: ellipsis` to card content.
- Fixed traffic page KPI cards overflowing their containers with explicit overflow containment.
- Reduced port table horizontal scroll by lowering `min-width` from `1140px` to `880px` (mobile: `780px`).

### Changed
- Modernized global CSS palette from icy blue to deeper indigo primary (`#4f46e5`) with teal accent (`#0d9488`) and Tailwind-inspired slate tones for stronger contrast.
- Strengthened soft-button color variants (primary/info/warning/success/danger) with higher-contrast backgrounds and borders.
- Dashboard Test Mode indicator now renders **above** KPI cards as a prominent flag pill instead of below.
- Dashboard flag pill styling upgraded with larger size, colored borders, status dot indicator, and box shadow.
- Table header backgrounds updated to stronger slate tone for improved visual hierarchy.
- Tab navigation hover/active styling updated to match new indigo palette.
- KPI icon colors per category updated (datacenters=indigo, racks=violet, switches=emerald, servers=blue, breaches=orange, queue=red).

### Added
- Added log-level row color tinting in Logs tab: error rows tinted red, warning rows amber, info rows blue, debug rows gray — with left border accent.
- Added queue job status row tinting: failed=red, pending=yellow, running=blue, done=green backgrounds for quick visual scanning.
- Added CSS classes for dashboard flag pills (`is-warning`, `is-success`, `is-danger`) with dot indicator and prominent styling.

## [0.1.75] - 2026-02-23
### Fixed
- Improved VLAN parsing fallback to detect additional interface formats (`Vl*`) and normalize numeric VLAN extraction when available.
- Fixed Monitoring and Servers black-screen behavior caused by forced static-open modal rendering.
- Reduced cases where server details routes became unstable by removing synchronous PRTG counter fetch calls from render path.
- Fixed malformed Monitoring markup that could break layout flow after opening add/view/edit dialogs.

### Changed
- Standardized create flows to modal UX in key pages:
  - Datacenters create
  - Switches create
  - Traffic Packages create
- Monitoring edit flow now uses modal UX (consistent with add/view behavior).
- Services/Group filter action label changed to explicit product loading action (`Load Products`).
- Traffic dashboard sort labels were rewritten to human-friendly wording.
- Improved global spacing/responsiveness (page width, table wrappers, action buttons, modal form spacing) to reduce side-scroll and overlap.

### Added
- Added i18n key for scoped product loading action in both English and Persian.

## [0.1.74] - 2026-02-23
### Changed
- Monitoring instance actions now keep context on the Monitoring tab and persist the active `View` modal state during mapping create/delete operations.
- Monitoring labels are now vendor-agnostic (`Monitoring URL`, `Monitoring Username`, `Monitoring Secret`) while still supporting PRTG-specific auth controls.
- Modal presentation was unified for Monitoring/Servers/Packages with shared spacing and button alignment styles.

### Added
- Added focused loading state UX for monitoring hierarchy mapping forms (row-level busy state with spinner).

### Fixed
- Fixed Server edit JavaScript selector escaping that was causing Safari/WebKit `SyntaxError: Invalid escape in identifier` and blocking edit interactions.
- Improved PRTG hierarchy filtering to reduce invalid/numeric/internal probe/group/device rows and de-duplicate noisy results.

## [0.1.73] - 2026-02-22
### Changed
- Servers table no longer renders inline row edit forms; server actions are now compact and include dedicated `View`, `Edit`, and `Delete`.
- Server details now use a single details workspace below the table with mode switching:
  - `View` (read-only)
  - `Edit` (editable with Save/Cancel flow)
- Server details UI sections were reorganized to be cleaner and easier to scan:
  - Overview
  - Network Control (single SNMP control port mapping)
  - Traffic links and aggregated counters (Download / Upload / Total)
  - Hardware/Traffic monitoring sensor sections
  - Public IP discovery/check block
  - WHMCS discovery status/logs visibility
  - iLO block in the same server workflow

### Added
- Added schema migration `v14` to extend `mod_dcmanage_server_traffic_sensors` with `sensor_type` for separating `traffic` and `hardware` sensor mappings.
- Server create/update now persists explicit control-port mapping (`action_switch_id` + `action_port_id`) and keeps compatibility fallback to first traffic link when no explicit control port is chosen.

### Fixed
- Improved server details spacing/alignment/button consistency with new dedicated server-details card styles.
- Monitoring row parser now supports `monitor_sensor_type[]` safely with validation and deduping by `(prtg_id, sensor_id, alert_action, sensor_type)`.

## [0.1.72] - 2026-02-22
### Fixed
- Fixed Safari/WebKit JS selector escaping in Rack/Switch/Servers scripts that caused `SyntaxError: Invalid escape in identifier` and blocked server edit interactions.
- Fixed `Services / Group` filter behavior to be strictly PID/GID CSV-driven (removed extra search dependency).
- Server create/update now updates action target (`action_switch_id` / `action_port_id`) from selected traffic links for consistent suspend/unsuspend targeting.
- Server delete now also cleans `mod_dcmanage_server_monitoring_links` when present.
- Auto-update status now auto-clears stale warning/error banners when installed version is already equal to latest release.
- PRTG probe listing now filters internal/non-root pseudo entries more strictly to reduce duplicated/invalid probe rows.

## [0.1.71] - 2026-02-22
### Changed
- `Services / Group` filtering was redesigned to use two CSV fields:
  - `GID List`
  - `PID List`
- Scope product loading behavior now follows:
  - GID-only => load all products in selected groups
  - PID-only => load selected products
  - GID + PID => load union of both
- Scope page no longer loads all products when no filter is provided.
- Group default quota forms now render for each selected GID (multi-group defaults in one screen).

### Fixed
- GID/PID filter now accepts Persian/Arabic digits in CSV input.

## [0.1.70] - 2026-02-22
### Added
- Added monitoring group mapping storage table: `mod_dcmanage_monitoring_group_map`.
- Added Monitoring mapping UI with `View` shortcut from instance list and mapping rows for:
  - traffic
  - hardware
  - public monitoring
  - client discovery
- Added per-server Discovery widget in Server edit form (target host + port list + run/check result).

### Changed
- Monitoring top action label changed from `Add PRTG Instance` to `Add Monitoring Instance`.
- Monitoring now supports mapping to switch context in UI so group strategy can be defined before server assignment.
- PRTG probe list now filters pseudo/internal numeric rows and keeps only valid probe-style entries.
- Discovery section moved out of Monitoring tab and into Servers workflow.

### Fixed
- Reduced wrong probe dropdown results where numeric/internal IDs were shown as probe names.

## [0.1.69] - 2026-02-22
### Added
- Added `Test Mode` in Settings (`settings.enforcement_test_mode`) to allow safe enforcement testing without executing real suspend/enforce operations.

### Changed
- Monitoring instance creation now supports non-PRTG providers (`SolarWinds`, `Cacti`) without forcing API secret at create time.
- Monitoring add form now labels secret field as `passhash / apitoken` for clearer operator input.
- PRTG client now normalizes base URL (removes `index.htm` / `home` suffixes) to prevent malformed API paths.
- PRTG auth fallback chain expanded to try:
  - `username + passhash`
  - `username + password`
  - `username + apitoken`
  - `apitoken`
- PRTG secret normalization now accepts prefixed values like `apitoken=...` and `passhash=...`.

### Fixed
- Reduced common `PRTG HTTP 401` causes for self-signed and mixed-auth deployments by improving URL and auth fallback handling.
- Usage engine now skips real enforce queue actions in Test Mode and logs test-mode breach events instead.

## [0.1.68] - 2026-02-22
### Added
- New `Services / Group` tab (replacing placeholder `WHMCS Scope`) with real product-scope management:
  - Filter by `GID` / `PID`.
  - Product list loaded from WHMCS products/groups.
  - Per-product default traffic limits for `Download`, `Upload`, `Total`.
  - Per-limit `Unlimited` toggles that disable numeric inputs in UI.
- Added group-level default scope form (`type=gid`) for quick defaulting across products in a group.
- Added Datacenter traffic calculation mode field in create/edit UI:
  - `Download`
  - `Upload`
  - `Total`

### Changed
- Usage engine now applies Datacenter traffic calculation mode during polling (`IN`/`OUT`/`TOTAL`) so quota enforcement follows selected datacenter policy.
- Usage engine now resolves base limits from scope hierarchy:
  - Product-level (`PID`) first,
  - then Group-level (`GID`) fallback.
- Scope/product labels updated in bilingual dictionary to match new naming (`Services / Group`).

### Fixed
- Datacenter listing query now includes `traffic_calc_mode` safely in grouped selection.
- Scope edit row UX now includes consistent checkbox/input spacing and inline action alignment.

## [0.1.67] - 2026-02-22
### Added
- Monitoring API now exposes PRTG hierarchy endpoints for structured browsing:
  - `prtg/probes`
  - `prtg/groups`
  - `prtg/devices`
  - `prtg/device-sensors`
- Added monitoring discovery endpoint (`monitoring/discover`) to test target host common TCP ports without `shell_exec`.
- Monitoring tab now includes:
  - unified list of monitoring instances (PRTG / SolarWinds / Cacti),
  - top add button and add form,
  - PRTG Browser (instance -> probe -> group -> subgroup -> device -> sensors),
  - discovery card for host/port checks.

### Changed
- PRTG credentials now support auth mode selection (`passhash` or `api_token`) and keep `Verify SSL` behavior for self-signed environments.
- Server traffic and monitoring mapping UX updated so search is embedded in the same selector block (no separate detached search column).
- Switch-port option labels in server mapping now include VLAN context when available.

### Fixed
- Improved PRTG HTTP 401 handling by adding resilient auth fallback attempts (`username+passhash`, `username+apitoken`, `apitoken`).
- Datacenter-dependent select filtering now rebuilds option lists by selected datacenter to prevent cross-DC rack/switch selection leakage.

## [0.1.66] - 2026-02-22
### Changed
- Introduced a global inline action bar style for consistent spacing/alignment of buttons and action links across forms/tables.
- Normalized secondary action button visual style (`Reset` and similar) to avoid plain-text rendering look.

### Fixed
- Queue filter action row spacing fixed (`Apply Filter`, `Reset`, `Clear Done/Failed`) so controls no longer collapse into each other.
- Logs action row switched to the shared inline action style for stable margins and alignment.

## [0.1.65] - 2026-02-22
### Fixed
- Traffic Packages edit form action button (`Save Settings`) now renders inside a proper action row and no longer overlaps input fields.
- Package row action buttons (`Edit` / `Delete`) are now vertically aligned and kept on one line for cleaner table layout.

### Changed
- Improved action button container styling by normalizing inline form/button alignment and margin behavior.

## [0.1.64] - 2026-02-22
### Added
- Server create/edit now supports row-based traffic mapping:
  - Multiple `Switch + Port` rows per server for aggregated traffic links.
  - Per-row port search (Persian/English-aware) in port selector.
- Server create/edit now supports row-based monitoring mapping:
  - Multiple monitoring rows with `Instance + Sensor + Alert Action`.
  - Alert actions: `None`, `Email`, `SMS`, `Email + SMS`, `Ticket`.
- Added schema migration `v9` to extend monitoring storage with `alert_action`.

### Changed
- Server edit flow now starts from datacenter and enforces chain selection order:
  - `Datacenter -> Rack -> Switch -> Port`.
- Server edit now persists core fields in one place (`datacenter`, `rack`, `hostname`, `U start/height`, `notes`, `iLO`) together with traffic/monitoring links.
- Legacy single-link/sensor payloads remain backward-compatible via parser fallbacks.

### Fixed
- Prevented cross-datacenter link mismatches by validating selected switch ports against selected datacenter during save.
- Improved server-side validation for rack ownership by datacenter on create/edit.

## [0.1.63] - 2026-02-22
### Changed
- VLAN visibility is now restricted to VLAN interfaces only (`Vlan*` / `Vlanif*`).
- Physical interfaces (e.g. `Ethernet`, `mgmt`, `10G/40G` ports) now keep VLAN field empty in switch ports view and persisted data.

### Fixed
- Prevented SNMP PVID-derived VLAN values from being shown on non-VLAN interfaces.

## [0.1.62] - 2026-02-22
### Added
- Implemented full `Traffic Packages` admin CRUD UI in addon:
  - Add package by Datacenter (required), Package Name, Traffic Unit (GB), Price, Taxed, Status.
  - Edit package with same fields.
  - Delete package with confirmation.
- Added bilingual labels/messages for package fields and actions in English/Persian.

### Changed
- `Traffic Packages` tab now renders real management UI instead of placeholder message.
- Package model is now explicitly Datacenter-scoped in UI for different pricing per datacenter.

## [0.1.61] - 2026-02-22
### Changed
- Servers tab now provides two top action buttons (`Add Server`, `Bulk Create Servers`) and each form is opened in its own collapsible section for cleaner workflow.
- Server form cards and inline server edit cards are now centered and visually aligned for easier editing.
- Removed `Asset Tag` and `Serial` fields from server create workflow as requested.

### Fixed
- Fixed Safari/WebKit selector escape issues in inline JS by replacing escaped attribute selectors, preventing `SyntaxError: Invalid escape in identifier` in server/rack/switch interactions.
- Switch edit form now filters Rack options by selected Datacenter (same behavior as create form).

### Added
- Datacenter edit now supports rack management fields (`Rack Count`, `Rack Units`) and can auto-create missing racks directly from edit.
- Rack edit now includes additional metadata fields (`Row`, `Rack`, `Notes`) and persists them.

## [0.1.60] - 2026-02-22
### Fixed
- Update runtime state now auto-clears stale error messages when the latest `update_apply` job has already completed successfully.
- Dashboard no longer keeps showing old `Update archive missing modules/addons/dcmanage` after subsequent successful update jobs.
- Runtime state now normalizes to `updated/idle` when installed version is already synced with latest cached release.

## [0.1.59] - 2026-02-22
### Fixed
- Fixed Servers tab inline JavaScript parse error in Safari/WebKit (`SyntaxError: Invalid escape in identifier`) caused by escaped quotes in pager logic.
- Server row `Edit` toggle works again after JS parse fix.

## [0.1.58] - 2026-02-22
### Fixed
- Auto-update package validation now correctly detects module path for both release asset layouts:
  - archive root contains `modules/addons/dcmanage`
  - archive contains a top-level folder that then contains `modules/addons/dcmanage`
- Resolved false update failure: `Update archive missing modules/addons/dcmanage`.
- Dashboard Cron Health `OK` state now renders with green status pill (instead of gray badge).
- Settings Cron Monitor status pills now use consistent green/yellow/red semantic colors.

### Changed
- Removed extra dashboard header text line (`DCManage / API-first Datacenter Core - vX.Y.Z`) as requested.

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
