# DCManage v1.0 Spec (Executable Foundation)

## Internal API routes
Base: `addonmodules.php?module=dcmanage&dcmanage_api=1&endpoint=`

- `dashboard/health` `GET`
- `datacenters/list` `GET`
- `traffic/list` `GET`
- `graphs/get` `GET` params: `service_id`, `from`, `to`, `avg`

## JSON contracts

### `dashboard/health`
```json
{
  "ok": true,
  "data": {
    "counts": {
      "datacenters": 0,
      "racks": 0,
      "servers": 0,
      "ports": 0,
      "jobs_pending": 0,
      "usage_breaches_today": 0
    },
    "integration_health": {
      "prtg_instances": 0,
      "ilos": 0
    },
    "last_cron_runs": []
  }
}
```

### `traffic/list`
```json
{
  "ok": true,
  "data": [
    {
      "service_id": 123,
      "status": "ok",
      "domain_status": "Active",
      "used_bytes": 0,
      "allowed_bytes": 0,
      "remaining_bytes": 0,
      "cycle_start": "2026-02-01 00:00:00",
      "cycle_end": "2026-02-28 23:59:59",
      "last_sample_at": "2026-02-21 12:00:00"
    }
  ]
}
```

## Cron tasks and timeout policy
- `poll_usage` every 5 min, lock TTL: 240s
- `enforce_queue` every 1 min, lock TTL: 55s
- `graph_warm` every 30 min, lock TTL: 1700s
- `cleanup` daily, lock TTL: 1800s

## Admin role permissions (recommended)
- `DCManage: View Dashboard`
- `DCManage: Manage Inventory`
- `DCManage: Manage Integrations`
- `DCManage: Manage Traffic`
- `DCManage: Run Automation`
- `DCManage: View Logs`

## Cycle semantics
- `cycle_end = nextduedate 00:00:00 - 1 second`
- `cycle_start = cycle_end - billing cycle length + 1 second conceptually`
- Early renewal does not move cycle until `nextduedate` changes.

## Queue semantics
Table: `mod_dcmanage_jobs`
- statuses: `pending`, `running`, `done`, `failed`
- backoff: `20s * attempts`, capped at `300s`
- max attempts: `5`
