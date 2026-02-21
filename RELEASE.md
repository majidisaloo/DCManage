# Release Process

## Versioning
- Semantic Versioning is used: `MAJOR.MINOR.PATCH`.
- Current release: `0.1.1`.

## Release checklist
1. Update `VERSION`.
2. Update `modules/addons/dcmanage/lib/Version.php`.
3. Append release notes to `CHANGELOG.md` with exact date.
4. Run static syntax checks (`php -l`) on module files.
5. Verify addon activation and upgrade path in staging.
6. Tag release in git: `vX.Y.Z` and push tag.
7. GitHub Actions `Release` workflow creates release asset `DCManage-vX.Y.Z.zip`.
8. Verify zip root contains only `dcmanage/` folder.

## Safe auto-update policy
- Only additive database migrations run in activation/upgrade path.
- Heavy backfills are moved to background cron tasks.
- Locks prevent parallel cron duplication during rollout.
- Queue-based actions avoid user-facing request blocking.
