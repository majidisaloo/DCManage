# DCManage (WHMCS Addon)

DCManage is an API-first datacenter management core inside WHMCS.

## Features in this release (v0.1.75)
- Datacenter domain model foundations:
  - Datacenters, Racks, Networks, Switches, Servers, Ports, iLO, PRTG mappings.
- Traffic/usage foundations:
  - `usage_state`, service linking, package/purchase models.
  - cycle resolver aligned to WHMCS `nextduedate`.
- Operations foundations:
  - DB-backed job queue (`mod_dcmanage_jobs`).
  - DB locks (`mod_dcmanage_locks`) for safe cron concurrency.
  - Structured logs (`mod_dcmanage_logs`).
- Graph foundations:
  - 2-month cache table and API endpoints to store/retrieve graph payloads.
- Bilingual admin UI:
  - Persian (`fa`) and English (`en`) labels/messages.
  - Automatic language detection from WHMCS session (`Language`/`adminlang`).
- Cron entrypoint with single command dispatcher:
  - Install one cron line only: `* * * * * php -q .../modules/addons/dcmanage/cron.php dispatcher`
  - Internal scheduler runs `poll_usage`, `enforce_queue`, `graph_warm`, `cleanup`, `switch_discovery`, and `self_update`.
- Dashboard update center:
  - Shows current installed version and latest GitHub release.
  - Supports one-click `Check Update` and one-click `Apply Update`.
  - Supports toggling Auto Update directly from dashboard.
  - Update status is now color-coded as requested:
    - red: not updated
    - yellow: update available
    - green: updated
  - API response extraction hardened for WHMCS-wrapped responses so dashboard update buttons load reliably.
  - Improved visual hierarchy with modern cards, aligned metrics, larger KPI icons, and semantic feedback states.
  - Fixed spacing and alignment in dashboard Version/Cron cards and update controls on mobile and desktop.
  - Empty update message area is now hidden until an actual status message exists.
- Dashboard operation center:
  - Shows counts for Datacenters, total Racks, Switches, Servers, Ports, queue, and breaches.
  - KPI cards navigate directly to related module tabs (queue card routes to Queue tab).
  - Dedicated Queue tab for job operations: filter/search/pagination + cancel/retry/clear actions.
  - KPI cards now include dedicated vector-style icons per parameter.
  - Standalone `Ports` KPI card was removed to keep port management consolidated under `Switches`.
  - Shows cron overall status (`green/yellow/red`) and per-task status.
- System Settings tab inside module:
  - timezone, locale, traffic poll interval, switch discovery interval, graph cache TTL, log retention, dashboard refresh interval.
  - cron commands with auto-detected real server path, last run, next run, and health check panel.
  - compatible with hardened PHP setups where shell-related functions are disabled.
  - resilient shell-quote fallback keeps settings/cron pages stable even when `escapeshellarg` is blocked by host policy.
- Inventory workflows:
  - Create forms for Datacenters, Switches, and Traffic Packages now open in consistent modals (no inline collapse forms).
  - Servers UI refactor:
    - Removed inline edit expansion from table rows.
    - Added per-row actions: `View`, `Edit`, `Delete` (with confirmation).
    - Added unified server details workspace with read-only/editable mode switching.
    - Added explicit single control-port mapping support for SNMP block/unblock target.
    - Added separated traffic/hardware sensor typing in server mappings.
    - Server details now include discovery status/log visibility and cleaner overview sections.
    - Server details modal now uses safe Bootstrap open/close flow (no forced static overlay), reducing black-screen behavior on `server_mode=view|edit`.
    - Removed synchronous PRTG counter pulls from server details render path to reduce page latency/timeouts.
  - Fixed Safari/WebKit JS parsing/selector escapes that were breaking server edit actions.
  - `Services / Group` scope now stays strictly PID/GID CSV based.
  - Server action port target now syncs from selected traffic link for enforce operations.
  - Stale update warning banner is auto-cleared when installed version already matches latest release.
  - `Services / Group` filter now uses CSV inputs for both `GID` and `PID`.
  - Supports combined scope loading:
    - only GIDs => all products of those groups
    - only PIDs => only those products
    - GIDs + PIDs => union (group products + explicit products)
  - Scope filter no longer loads all products by default when no GID/PID/filter is provided.
  - GID/PID parser now supports Persian/Arabic digits in CSV input.
  - Group default limit forms are rendered per selected GID (multi-group edit in one page).
  - Monitoring tab top action now uses generic `Add Monitoring Instance` label for multi-platform usage.
  - Monitoring Edit now opens in modal mode, and View/Edit routes auto-open matching modal then cleanly return to tab URL after close.
  - Added Monitoring `View` flow and group mapping layer (per instance/per switch) for purposes:
    - Traffic
    - Hardware
    - Public Monitoring
    - Client Discovery
  - Monitoring group mapping supports multiple rows so each purpose can have one or more mapped groups/devices.
  - PRTG probe loading now filters internal/numeric pseudo probes and keeps only real probe rows.
  - Discovery block moved from Monitoring tab into Server edit flow (per-server context).
  - `Services / Group` tab now provides real PID/GID product scope management with per-product download/upload/total defaults and unlimited toggles.
  - Datacenter create/edit now includes traffic calculation mode (`Download` / `Upload` / `Total`) used by usage enforcement.
  - Monitoring tab now supports multi-platform instance registry (`PRTG`, `SolarWinds`, `Cacti`) with a unified top add/list workflow.
  - Monitoring create flow now allows non-PRTG provider records without mandatory PRTG secret.
  - PRTG auth compatibility improved for `passhash`/`apitoken`/fallback combinations and normalized base URLs.
  - Added Settings `Test Mode` toggle so enforce/suspend actions can be tested safely before production enforcement.
  - Added PRTG hierarchy browser flow for probe/group/subgroup/device/sensor traversal.
  - Added built-in discovery check (host + common TCP ports) for monitoring pre-validation without shell functions.
  - PRTG auth now supports both `Username + Passhash` and `API Token` modes with 401 fallback strategy.
  - Server traffic/monitor selector search is now embedded inside the same selector block for faster operator flow.
  - Added shared inline action-bar defaults for consistent spacing/alignment across module controls.
  - Fixed Queue/Logs action rows so filter/reset/clear buttons render with stable spacing and proper button visuals.
  - Fixed package edit action layout so `Save Settings` stays in its own action row.
  - Fixed package table action alignment so `Edit` and `Delete` remain properly aligned in one line.
  - Server create/edit now supports row-based traffic port links (multi `Switch + Port` per server).
  - Server create/edit now supports row-based monitoring sensor links (multi `Instance + Sensor + Alert Action` rows).
  - Server edit now includes full core fields (`Datacenter`, `Rack`, `Hostname`, `U`, `Notes`, `iLO`) in the same save flow.
  - Datacenter to rack/switch/port dependency is enforced in edit forms too.
  - Port selector now supports Persian/English-aware live search.
  - Servers page now uses two dedicated top actions (`Add Server`, `Bulk Create Servers`) with separate collapsible forms instead of stacked mixed layout.
  - Server create/edit forms are centered for cleaner operator workflow.
  - Server create flow no longer includes `Asset Tag` and `Serial`.
  - Datacenter edit now includes rack controls (`Rack Count` and `Rack Units`) and can create missing racks directly from edit.
  - Rack edit now supports `Row`, `Rack`, and `Notes` fields in addition to name/U.
  - Switch edit now filters rack list by selected datacenter (same behavior as add switch).
  - Fixed Safari/WebKit `Invalid escape in identifier` in dynamic selectors for servers/racks/switches.
  - Fixed Safari/WebKit JS parsing issue in Servers tab that blocked table actions.
- Traffic package management:
  - `Traffic Packages` tab now has full CRUD (add/edit/delete).
  - Package creation is datacenter-scoped (different package pricing per datacenter).
  - Package fields now include:
    - Datacenter
    - Package Name
    - Traffic Unit (GB)
    - Price
    - Taxed (for invoice tax behavior)
    - Active/Inactive status
- Switch VLAN display logic:
  - VLAN value is now shown only for VLAN interfaces (`Vlan*` / `Vlanif*`).
  - Physical interfaces (`Ethernet`, `mgmt`, uplinks, 10G/40G ports) keep VLAN column empty.
  - Server `Edit` row toggle is restored and works correctly again.
  - Live table search now normalizes Persian/Arabic digits and common Arabic/Persian letter variants for reliable matching in mixed-language input.
  - Servers table pagination moved to bottom and now defaults to 15 items with user-selectable page size (10/15/25/50/100).
  - Server create form now strictly enforces dependency order: Datacenter first, then Rack/Switch, then Switch Port.
  - Switch Ports table layout is now fixed-width for stable column alignment across variable description lengths.
  - Switch Ports pagination controls moved to bottom of the table.
  - Switch Ports pagination now includes per-page selector (default 15; supports 10/15/25/50/100).
  - Datacenter create supports auto rack generation by count + rack unit size.
  - Datacenters page no longer repeats a secondary `Datacenters` heading below tabs.
  - Datacenters action row has increased top margin for cleaner spacing above `Add Datacenter`.
  - Datacenters block now has balanced top/bottom/left/right spacing from border lines.
  - Datacenter add form is opened by Add button (collapsed by default).
  - Datacenter page shows rack U-level maps and occupancy.
  - Switch create supports Datacenter -> Rack dependent selection.
  - Switch create supports optional U position for rack placement.
  - Server create flow is dependency-first:
    - Datacenter -> Rack (same datacenter only)
    - Switch (same datacenter only)
    - Switch Port (only selected switch ports, loaded on-demand)
  - Added bulk server creation by hostname range (example: `MDP-301` -> `MDP-399`) with duplicate-skip behavior.
  - Traffic sensor mapping moved to post-create server edit panel for safer operations in multi-datacenter environments.
  - Dashboard update panel now keeps result message hidden by default and only shows it after update actions.
  - Auto Update checkbox row spacing/alignment improved to avoid crowded text/checkbox rendering.
  - Bulk server section hint and action button spacing is normalized so example text and action button never stick together.
  - Bulk server creation is now datacenter-level and does not request rack; bulk-created servers stay unassigned to rack for later controlled placement.
  - Manual Add/Update Port form was removed from Switches panel; ports are maintained via SNMP discover/sync.
  - Port admin state is now shown as `Active/Suspended` (instead of `No Shut/Shut`) with `Activate/Suspend` actions.
  - Added per-port instant check action to refresh only that selected port from SNMP.
  - Absent-state parsing improved for additional raw values (`abcent`, platform-specific absent strings).
  - Top navigation spacing was normalized so tabs are no longer visually glued to content sections below.
  - Server create form now enforces strict dependency:
    - no datacenter -> rack/switch/port disabled
    - switch ports load only after both datacenter and switch are selected
  - SNMP speed parser now normalizes abnormal raw values and prevents outputs like `2140046M`.
  - Switch ports panel now includes live search by interface name, description, and VLAN.
  - iLO is now managed directly inside Servers create/edit forms (one iLO per server mapping).
  - Standalone iLO tab was removed from top navigation and merged into server workflow.
  - Servers list now includes delete action with linked mapping cleanup.
  - Settings now include iLO proxy configuration and test (`http`, `https`, `socks5`).
  - Fixed server edit JS crash caused by malformed escaping (`Invalid escape in identifier`).
  - Updater is now queue-based with live status polling and cancel support to prevent overlapping update tasks.
  - Updater validates release package completeness and version consistency before apply.
  - Updater now includes stale-job cleanup and fallback cached release metadata to avoid false 400 responses and stuck cancel states.
  - Form/card spacing is normalized across settings and admin forms (padding, row gaps, and action button spacing).
  - Logs now support search, level/source filters, level sorting, pagination, and clear actions.
  - Servers and Switch Ports tables now support live search and pagination for large datasets.
  - VLAN detection improved for hex SNMP values and single-port check fallback mapping.
  - Dispatcher cron now writes per-task completion logs so Cron Health reflects actual task runs.
  - Dispatcher now continues running remaining tasks even if one task fails.
  - Per-server edit panel now supports switch/port remap + PRTG sensor selection/update.
  - Monitoring tab now includes PRTG instance management (add/list/test/delete).
  - Datacenter rows now expose direct actions: Racks, Servers, Edit, Delete.
  - Rack map is front-focused with click-to-select U workflow and per-U marking for reserved/cable/airflow/blank planning.
  - Rack UI is now compact and card-based with a tower-style layout (no oversized stretched rows).
  - Rack cards support inline rack rename and total-U updates.
  - Switch section now supports collapsible add flow, SNMP fields, and per-switch SNMP connectivity test with status badges.
  - Switch section includes ports/VLAN inventory with per-port `Check Port`, `Suspend`, and `Activate` actions.
  - Switch section now supports one-click `Discover Ports` via SNMP walk (auto-import interface/admin/oper/vlan when available).
  - Switch discovery now imports interface descriptions (`ifAlias`) and stores interface index for better mapping.
  - Switch discovery now imports port speed profile and mode and shows it as:
    - `1G`, `10G`
    - `Auto 1G`, `Auto 10G`
  - Top navigation no longer includes `Ports`; all port operations are under `Switches`.
  - Servers list now shows linked switch/port and mapped sensor count preview.
  - Switch SNMP test now auto-discovers and stores ports when connection succeeds.
  - SNMP compatibility added for environments that do not provide `snmp2_real_walk` but expose `snmprealwalk` or `snmp2_walk/snmpwalk`.
  - VLAN resolution is improved using `dot1dBasePortIfIndex` -> `dot1qPvid` mapping for broader switch compatibility.
  - Switch and port connectivity states now use explicit green/red status pills (instead of gray badges) for clearer operational visibility.
  - Port status terminology updated to operational wording:
    - `Active / Suspended` for config state
    - `Connected / Not Connected / SFP Absent` for link state
  - Removed duplicated in-page section titles that matched active tabs (Switches/Servers/Monitoring), keeping only top navigation tab title.
  - Global spacing was normalized across all pages to keep controls and table borders visually separated.
  - Added consistent table wrappers in key tabs to prevent top-line/button collisions.
  - Improved responsive layout for both large monitors (wide-screen tuning) and mobile (compact spacing and full-width controls).
  - Dashboard Auto Update control now uses a unified inline checkbox style for consistent UI with action buttons.
  - Global section spacing was further refined so top action buttons never touch the next table border line.
  - Dashboard ports counter now includes both switch ports and server ports.
  - Switch action buttons now use explicit submit-bound action routing to prevent wrong action execution in wrapped WHMCS forms.
- Logs:
  - package purchase logs are now visible inside Logs tab.
- Cron health compatibility:
  - Cron monitor now recognizes both dispatcher task logs and legacy execution logs, so status is accurate after upgrades.
- Update status cleanup:
  - stale cancel messages are auto-cleared when no active update job exists, so dashboard update alert does not remain yellow incorrectly.
- Settings UI:
  - iLO proxy action buttons spacing/layout normalized.
- Visual enhancements:
  - top menu tabs now have compact vector icons next to each label.
  - dashboard middle section includes a vector hero strip and icon watermarks on KPI cards.
- Switch VLAN discovery:
  - VLAN extraction now supports SVI interface patterns like `Vlan10` / `vlanif10` when native PVID SNMP mapping is not provided by device.

## Installation From GitHub Releases
1. Download asset: `DCManage-vX.Y.Z.zip` from repository Releases.
2. Upload zip to WHMCS root (`public_html`).
3. Extract zip in `public_html`.
4. The zip automatically places files at `modules/addons/dcmanage`.
5. Activate addon in WHMCS Admin > System Settings > Addon Modules.
6. Grant admin role access to `DCManage`.

## Cron Setup
Add these server cron entries:
```bash
*/5 * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php poll_usage
* * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php enforce_queue
*/30 * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php graph_warm
12 2 * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php cleanup
*/5 * * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php switch_discovery
30 3 * * * php -q /path/to/whmcs/modules/addons/dcmanage/cron.php self_update
```

## Auto Update
The addon can pull new releases from GitHub automatically without `shell_exec`.
- Module settings:
  - `Enable Auto Update`: on/off
- Runtime behavior:
  - GitHub source is hardcoded to `majidisaloo/DCManage` (`main` policy).
  - `self_update` cron checks `releases/latest`.
  - If release tag version is newer than installed version, zip is downloaded and files are replaced in `modules/addons/dcmanage`.
  - Lock `cron:self_update` prevents parallel update runs.

## Legacy manual install
1. Copy `modules/addons/dcmanage` into WHMCS root.
2. Activate addon in WHMCS Admin > System Settings > Addon Modules.
3. Grant admin role access to `DCManage`.

## Upgrade and zero-downtime strategy
- Versioned migrations run in `dcmanage_upgrade($vars)`.
- Additive schema-first updates only in normal upgrades.
- Destructive cleanup only in maintenance release and never in request path.
- Cron-safe locks prevent overlapping poll/enforce jobs.
- Queue-based operations keep UI responsive and avoid blocking admin pages.

## API-first internal design
Admin UI can call internal JSON routes via:
- `addonmodules.php?module=dcmanage&dcmanage_api=1&endpoint=...`

Example endpoints:
- `dashboard/health`
- `dashboard/version`
- `dashboard/cron`
- `update/check`
- `update/apply`
- `update/set-auto`
- `datacenters/list`
- `traffic/list`
- `graphs/get`
- `switch/ports`

## Security notes
- `shell_exec` is not used.
- Sensitive fields are encrypted through WHMCS local API (`EncryptPassword`/`DecryptPassword`) fallback to OpenSSL.

## Bootstrap 4
The admin shell uses Bootstrap 4-compatible markup and responsive layout.

## Language behavior
- Language setting supports `Default`, `Persian`, and `English`.
- In `Default` mode, module language follows WHMCS admin/session language.
- If the account/session language is Persian, module UI labels load in Persian.
- If the account/session language is English, module UI labels load in English.
- Persian menu/tabs are rendered RTL and start from the right side.
- Optional override for testing: add `&lang=fa` or `&lang=en` to addon URL.

## Next implementation phases
1. Full CRUD forms + validations for all tabs.
2. PRTG sensor picker UI with search by group/device.
3. Nexus/iLO job workers and enforce/unlock actions.
4. Auto-buy invoice workflow and credit application.
5. Client-area widgets and advanced charts.
  - Auto-update package path detection is now compatible with both release zip layouts (root or nested top folder) to prevent false `missing modules/addons/dcmanage` errors.
  - Update runtime state now auto-cleans stale failure messages after a later successful update job.
  - Removed redundant dashboard header subtitle/version text block for a cleaner top section.
  - Cron health `OK` state now uses green semantic status pills across dashboard and settings.
