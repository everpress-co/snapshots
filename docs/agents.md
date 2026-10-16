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

JSON rows include `name`, `file` (folder id), `created`, and `past`. Inspect this before restore or delete.

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
wp snapshot restore "<name>"
```

You can pass the display name, slug, or folder id such as `before-the-experiment_1587039434`. A name restores the **latest** copy of that name.

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
wp snapshot delete "<name>"
```

Only delete when the user asks. Deletion is permanent on this server.

## Suggested workflow

1. `wp snapshot list --format=json` — see existing snapshots.
2. `wp snapshot backup "Before …" --files` — save state before your change.
3. Do the requested work.
4. If the user wants to undo, or your snapshot was for a failed experiment: `wp snapshot restore "Before …"`.

## Storage and retention

- Default folder: `wp-content/.snapshots` (override with `SNAPSHOTS_FOLDER` or **Settings → Snapshots**).
- Default retention: two copies per name (`SNAPSHOTS_MAX_SHOTS`). Older copies with the same name are removed automatically.

## Requirements you cannot bypass

- WP-CLI must be installed and runnable (often `SNAPSHOTS_CLI_PATH` in `wp-config.php`).
- PHP `exec` must be allowed.
- The web user must be able to run `php` (often `SNAPSHOTS_PHP_PATH`).

If backup or restore fails, read the error output. Do not retry restore in a loop without understanding the failure.

## More documentation

- Product overview: <https://everpress-co.github.io/snapshots/>
- Plugin readme: <https://github.com/everpress-co/snapshots/blob/develop/README.md>
