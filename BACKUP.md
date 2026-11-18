# BK&RS Dashboard — Backup & Restore

## Restore points (git tags)
| Tag | What it is |
|---|---|
| `stable-2026-11-18` | Everything working as of 18 Nov 2026 (tabs 1-10, sync button, FT row, tab 8 filters + summary table) |

List all: `git tag -l`. A tag is never moved or overwritten.

### Restore the dashboard page to a tag
```bash
git clone https://github.com/iksonlinemkt/booking-dashboard && cd booking-dashboard
git checkout stable-2026-11-18 -- index.html
git commit -m "Restore index.html from stable-2026-11-18" && git push origin main
```
Or on GitHub: open the tag -> open `index.html` -> Raw -> copy back.

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
