# SnapShots agent guide

Use this guide when SnapShots is active on a WordPress development or local site. SnapShots is not an off-site backup. Restores overwrite data on the same server.

## When to use snapshots

- Before database changes, bulk content edits, plugin experiments, or migrations you may need to undo.
- When the user asks you to try something reversible on a dev site.

Do not use SnapShots as a substitute for production backups or remote disaster recovery.

## Commands

Run these from the WordPress root (where `wp-config.php` lives).

### List snapshots

```bash
wp snapshot list --format=json
```

JSON rows include `name` (the text you passed to backup), `file` (folder id), `created`, and `past`. Inspect `file` before restore or delete. Backup stores that id as a slug plus a timestamp, such as `before-the-experiment_1587039434`.

### Create a snapshot

```bash
wp snapshot backup "Before <short task description>" --files
```

- Use a clear, unique name tied to the task.
- Add `--files` when uploads matter for the change (media, uploads-dependent behavior).
- Optional: `--location=wp-admin/plugins.php` to store a return URL.
- Optional: `--exclude_tables=table_one,table_two` on backup.

### Restore

```bash
wp snapshot restore before-the-experiment_1587039434
wp snapshot restore before-the-experiment
wp snapshot restore "Before the experiment"
```

Pass the full folder id from `file` to restore that exact copy. A missing folder id fails; it does not fall back to another snapshot. Pass the slug or display name without a timestamp to restore the newest-created matching snapshot. Display names are sanitized the same way as on backup.

Restore only:

- A snapshot **you** created for the current task, or
- When the **user** explicitly asks to restore.

A restore:

- Replaces the database from `dump.sql`.
- Drops database tables with this site's prefix that are **not** in the snapshot.
- Replaces the uploads folder only when that snapshot included files (`data.zip`).
- Does **not** restore plugins, themes, or WordPress core. Code on disk may no longer match the restored database.
- May run `search-replace` when the snapshot home URL differs from the current site URL.

### Delete

```bash
wp snapshot delete <slug-or-folder-id>
```

Only delete when the user asks. Deletion is permanent on this server.

## Suggested workflow

1. `wp snapshot list --format=json` — see existing snapshots.
2. `wp snapshot backup "Before …" --files` — save state before your change.
3. Do the requested work.
4. If the user wants to undo, or your snapshot was for a failed experiment: `wp snapshot list --format=json`, then `wp snapshot restore` with the exact `file` id for that copy, or the slug alone for the newest-created matching snapshot.

## Storage and retention

- Default folder: `wp-content/.snapshots` (override with `SNAPSHOTS_FOLDER` or **Settings → Snapshots**).
- Default retention: two copies per name (`SNAPSHOTS_MAX_SHOTS`). Older copies with the same name are removed automatically.

## Requirements you cannot bypass

- WP-CLI must be installed and runnable (often `SNAPSHOTS_CLI_PATH` in `wp-config.php`).
- PHP `exec` must be allowed.
- The web user must be able to run `php` (often `SNAPSHOTS_PHP_PATH`).

If backup or restore fails, read the error output. Do not retry restore in a loop without understanding the failure.

## More documentation

- Human guide: <https://snapshots.everpress.co/guide.html>
- Product overview: <https://snapshots.everpress.co/>
- Plugin readme: <https://github.com/everpress-co/snapshots/blob/develop/README.md>
