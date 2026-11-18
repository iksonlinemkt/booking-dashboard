# BK&RS Dashboard — Backup & Restore

## Restore points
| Restore point | What it is |
|---|---|
| `backups/index.stable-2026-11-18.html` | Frozen full copy of the dashboard page, everything working as of 18 Nov 2026 (tabs 1-10, sync button, FT row, tab 8 filters + summary table). Never edited. |
| git commit `226d525` | Same page state, in git history |

Rule: before any larger change, a new frozen copy `backups/index.stable-<date>.html` is added. Older copies are never overwritten.

### Restore the dashboard page
```bash
git clone https://github.com/iksonlinemkt/booking-dashboard && cd booking-dashboard
cp backups/index.stable-2026-11-18.html index.html
git add index.html && git commit -m "Restore index.html from stable-2026-11-18" && git push origin main
```
Or on GitHub: open `backups/index.stable-2026-11-18.html` -> Raw -> copy everything -> paste into `index.html` -> Commit.

### Undo one bad change without losing the rest
```bash
git log --oneline          # find the commit
git revert <commit>        # creates a new commit that undoes only that one
git push origin main
```

## Server side (VPS 187.127.108.153)
- `server/sync.php`, `server/nginx-api.ikssa.io.conf` — copies of the live files (no passwords).
- `api.php` and `db_config.php` (holds the DB password) are NOT stored here; they live in the VPS backups at `/root/backups/` (see below).
- Daily VPS backup: MySQL dump + `/var/www/iksdashboard` + nginx/apache config -> `/root/backups/iks-YYYY-MM-DD_HHMM.tar.gz` (keeps the last 14).

### Restore the VPS
```bash
cd /root/backups && ls -lt | head          # pick a file
mkdir /tmp/r && tar xzf iks-XXXX.tar.gz -C /tmp/r && cd /tmp/r/*
mysql iksdashboard < iksdashboard.sql
cp -a iksdashboard/. /var/www/iksdashboard/ && chown -R www-data:www-data /var/www/iksdashboard
cp nginx-api.ikssa.io /etc/nginx/sites-available/api.ikssa.io && nginx -t && systemctl reload nginx
```
