# DCManage (WHMCS Addon)

DCManage is an API-first datacenter management core inside WHMCS.

## Features in this release (v0.1.22)
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
- Cron entrypoint with 6 tasks:
  - `poll_usage` (every 5 min)
  - `enforce_queue` (every 1 min)
  - `graph_warm` (every 30 min)
  - `cleanup` (daily)
  - `switch_discovery` (every 5 min scheduler, interval-aware by settings)
  - `self_update` (daily/weekly)
- Dashboard update center:
  - Shows current installed version and latest GitHub release.
  - Supports one-click `Check Update` and one-click `Apply Update`.
  - Supports toggling Auto Update directly from dashboard.
  - Update status is now color-coded as requested:
    - red: not updated
    - yellow: update available
    - green: updated
  - API response extraction hardened for WHMCS-wrapped responses so dashboard update buttons load reliably.
  - Improved visual hierarchy with modern cards, aligned metrics, and semantic feedback states.
- Dashboard operation center:
  - Shows counts for Datacenters, total Racks, Switches, Servers, Ports, queue, and breaches.
  - KPI cards navigate directly to related module tabs (queue card routes to Settings).
  - KPI cards now include dedicated vector-style icons per parameter.
  - Shows cron overall status (`green/yellow/red`) and per-task status.
- System Settings tab inside module:
  - timezone, locale, traffic poll interval, switch discovery interval, graph cache TTL, log retention, dashboard refresh interval.
  - cron commands with auto-detected real server path, last run, next run, and health check panel.
  - compatible with hardened PHP setups where shell-related functions are disabled.
  - resilient shell-quote fallback keeps settings/cron pages stable even when `escapeshellarg` is blocked by host policy.
- Inventory workflows:
  - Datacenter create supports auto rack generation by count + rack unit size.
  - Datacenter add form is opened by Add button (collapsed by default).
  - Datacenter page shows rack U-level maps and occupancy.
  - Switch create supports Datacenter -> Rack dependent selection.
  - Switch create supports optional U position for rack placement.
  - Server create supports Datacenter -> Rack dependent selection and U position fields.
  - Datacenter rows now expose direct actions: Racks, Servers, Edit, Delete.
  - Rack map is front-focused with click-to-select U workflow and per-U marking for reserved/cable/airflow/blank planning.
  - Rack UI is now compact and card-based with a tower-style layout (no oversized stretched rows).
  - Rack cards support inline rack rename and total-U updates.
  - Switch section now supports collapsible add flow, SNMP fields, and per-switch SNMP connectivity test with status badges.
  - Switch section includes ports/VLAN inventory with add/update operations and per-port `Shut` / `No Shut` actions.
  - Switch section now supports one-click `Discover Ports` via SNMP walk (auto-import interface/admin/oper/vlan when available).
  - Switch discovery now imports interface descriptions (`ifAlias`) and stores interface index for better mapping.
  - Switch SNMP test now auto-discovers and stores ports when connection succeeds.
  - SNMP compatibility added for environments that do not provide `snmp2_real_walk` but expose `snmprealwalk` or `snmp2_walk/snmpwalk`.
  - VLAN resolution is improved using `dot1dBasePortIfIndex` -> `dot1qPvid` mapping for broader switch compatibility.
  - Switch and port connectivity states now use explicit green/red status pills (instead of gray badges) for clearer operational visibility.
  - Dashboard ports counter now includes both switch ports and server ports.
  - Switch action buttons now use explicit submit-bound action routing to prevent wrong action execution in wrapped WHMCS forms.
- Logs:
  - package purchase logs are now visible inside Logs tab.

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
