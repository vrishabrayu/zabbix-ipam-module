# IPAM – Zabbix Module

A full-featured IP Address Management module for Zabbix, integrated directly into the Inventory menu.

---

cd ~/zabbix-docker/zabbix-ipam-module
git pull


sudo docker cp ~/zabbix-docker/zabbix-ipam-module/. \
    zabbix-web:/usr/share/zabbix/modules/zabbix-ipam-module

sudo docker exec -u root zabbix-web chown -R 1997:1997 /usr/share/zabbix/modules/zabbix-ipam-module
## ⚡ Quick Start

### 1. Copy module files

```bash
cp -r zabbix-ipam-module /usr/share/zabbix/modules/
```

### 2. Create the database tables ← **do this first, or you will see errors**

```bash
mysql -u <zabbix_user> -p <zabbix_database> \
  < /usr/share/zabbix/modules/zabbix-ipam-module/sql/schema.sql
```

Find your DB credentials in `/etc/zabbix/zabbix_server.conf` — look for `DBUser`, `DBPassword`, and `DBName`.

### 3. Enable the module in Zabbix

1. Log in to Zabbix as a Super Admin
2. Go to **Administration → General → Modules**
3. Click **Scan directory**
4. Find **IPAM** and click **Enable**

### 4. Navigate to the module

Go to **Inventory → IPAM** in the left sidebar.

---

## 📡 Network Scan (nmap) — Install & Usage

IPAM discovers live hosts in a subnet by running **nmap** from inside the `zabbix-web` container. Without nmap installed, clicking **Scan** on a subnet will fail or return no results.

### Install nmap inside the Docker container

```bash
# Install nmap inside the running zabbix-web container
sudo docker exec -u root zabbix-web apt-get update
sudo docker exec -u root zabbix-web apt-get install -y nmap

# Verify it installed correctly
sudo docker exec zabbix-web nmap --version
```

> ⚠️ This install does **not** persist across container recreation (`docker-compose down && up`). If you rebuild the container, re-run the install command above, or bake nmap into a custom Dockerfile (see below).

### Make nmap persist (recommended) — custom Dockerfile

Create a `Dockerfile` that extends the official Zabbix web image:

```dockerfile
FROM zabbix/zabbix-web-nginx-mysql:latest

USER root
RUN apt-get update && apt-get install -y nmap && rm -rf /var/lib/apt/lists/*
USER zabbix
```

Then in `docker-compose.yml`, point the `zabbix-web` service at this Dockerfile instead of the public image:

```yaml
services:
  zabbix-web:
    build:
      context: .
      dockerfile: Dockerfile
    # ...rest of your existing config
```

Rebuild once:

```bash
sudo docker-compose up -d --build zabbix-web
```

nmap now survives container restarts and rebuilds.

### How to run a scan

1. Go to **Inventory → IPAM → Subnets**
2. Find the subnet you want to scan
3. Click **Scan** in the Actions column
4. IPAM runs `nmap -sV -O -T4 -v -n <subnet>/<cidr>` (service/version detection + OS fingerprinting) against every address in that range — this is a deeper scan than a plain ping sweep, so it takes longer per subnet
5. Responding IPs are marked **Used** (red), non-responding IPs are marked **Free** (green)
6. Check **Reports → Recent Scans** for scan history and results

### Test nmap manually (troubleshooting)

```bash
# Run the exact command IPAM uses, to confirm nmap works from inside the container
sudo docker exec zabbix-web nmap -sV -O -T4 -v -n 192.168.1.0/24
```

> ⚠️ **`-O` (OS detection) requires root privileges.** If the web server runs as a non-root user (commonly `www-data` or UID 1997), `-O` will silently fail or return no OS guesses. To allow OS detection without running the whole web server as root, grant the `nmap` binary the `cap_net_raw` and `cap_net_admin` capabilities inside the container:
>
> ```bash
> sudo docker exec -u root zabbix-web setcap cap_net_raw,cap_net_admin+eip $(sudo docker exec zabbix-web which nmap)
> ```
>
> This lets the non-root web server user run raw-socket scans (`-O`, `-sS`, etc.) without full root access.

If this works from the shell but scans still fail from the UI, check:
- The web server user (often `www-data` or UID 1997) has permission to execute `nmap` — test with `sudo docker exec -u www-data zabbix-web nmap --version`
- PHP's `exec()`/`shell_exec()` is not disabled in `php.ini` (`disable_functions`)

---

## 🔴 Common Error: "Table doesn't exist"

```
Error in query [SELECT COUNT(*) AS total FROM ipam_subnets]
Table 'zabbix.ipam_subnets' doesn't exist
```

**Cause:** You enabled the module in Zabbix before running the SQL schema.

**Fix:** Run step 2 above, then reload the IPAM page.

---

## Features

- **Dashboard** — subnet count, IP utilisation, top subnets, recent scans
- **Subnets** — add/edit/delete subnets with automatic host-address generation
- **IP Addresses** — browse, filter, edit hostname/MAC/owner per IP
- **Network Overview** — per-subnet detail view
- **VLANs** — manage VLAN database linked to subnets
- **Reports** — CSV export of utilisation, free IPs, or reserved IPs
- **Zabbix Sync** — link IPAM IPs to Zabbix hosts automatically
- **Network Scan** — ping-based scan to mark IPs used/free

---

## Requirements

| Component | Minimum |
|-----------|---------|
| Zabbix    | 6.0 LTS |
| PHP       | 8.0     |
| MySQL/MariaDB | 5.7 / 10.3 |

---

## Permissions

- **All users** — read-only access (browse subnets, IPs, VLANs)  
- **Zabbix Admin / Super Admin** — full write access

---

## License

MIT
