# SnapShots agent guide

Use this guide when SnapShots is active on a WordPress development or local site. SnapShots is not an off-site backup. Restores overwrite data on the same server.

## When to use snapshots

- Before database changes, bulk content edits, plugin experiments, or migrations you may need to undo.
- When the user asks you to try something reversible on a dev site.

Do not use SnapShots as a substitute for production backups or remote disaster recovery.

## Commands

Prefer **WP-CLI** when you have a shell on the WordPress root (where `wp-config.php` lives). On WordPress 6.9+, you may instead use the **Abilities API** abilities below when the host exposes them (for example via `wp-abilities/v1` or an MCP bridge). Both paths require the same server setup (WP-CLI, `exec`, PHP). Ability callers need a user with `manage_options`.

REST runs use `GET …/abilities/snapshots/list/run` for list, and `POST …/abilities/snapshots/backup/run`, `…/restore/run`, and `…/delete/run` with JSON body `{ "input": { … } }`. Delete is destructive but not idempotent (a slug can match more than one folder), so it uses POST like restore—not DELETE.

### List snapshots

```bash
wp snapshot list --format=json
```

Ability: `snapshots/list` (optional input `name` to filter). Output rows match CLI JSON: `name`, `file`, `created`, `past`.

JSON rows include `name` (the text you passed to backup), `file` (folder id), `created`, and `past`. Inspect `file` before restore or delete. Backup stores that id as a slug plus a timestamp, such as `before-the-experiment_1587039434`.

### Create a snapshot

```bash
wp snapshot backup "Before <short task description>" --files
```

Ability: `snapshots/backup` with input `{ "name": "Before …", "files": true }` (optional `location`). Creating a snapshot may permanently remove older copies with the same name once `max_shots` retention is exceeded.

- Use a clear, unique name tied to the task.
- Add `--files` / `"files": true` when uploads matter for the change (media, uploads-dependent behavior).
- Optional: `--location=wp-admin/plugins.php` / `"location"` to store a return URL.
- Optional: `--exclude_tables=table_one,table_two` on backup (CLI only; abilities use the site setting).

### Restore

```bash
wp snapshot restore before-the-experiment_1587039434
```

Ability: `snapshots/restore` with input `{ "id": "before-the-experiment_1587039434" }`.

Pass the full folder id from `file`. This restores that exact snapshot and avoids ambiguous slug matches.

Do not pass the display name from `name` when it contains capitals or spaces. `wp snapshot restore "Before the experiment"` does not match the folder `before-the-experiment_1587039434`.

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

Ability: `snapshots/delete` with input `{ "id": "<slug-or-folder-id>" }`. Prefer the exact `file` id from list. A slug deletes the newest matching snapshot; repeating the same slug can delete another.

Only delete when the user asks. Deletion is permanent on this server.

## Suggested workflow

1. List — `wp snapshot list --format=json` or ability `snapshots/list` — see existing snapshots.
2. Backup — `wp snapshot backup "Before …" --files` or ability `snapshots/backup` — save state before your change.
3. Do the requested work.
4. If the user wants to undo, or your snapshot was for a failed experiment: list again, then pass the exact `file` id to `wp snapshot restore` or ability `snapshots/restore`.

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
