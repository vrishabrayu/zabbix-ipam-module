# IPAM Pro – Zabbix Module

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
4. Find **IPAM Pro** and click **Enable**

### 4. Navigate to the module

Go to **Inventory → IPAM Pro** in the left sidebar.

---

## 🔴 Common Error: "Table doesn't exist"

```
Error in query [SELECT COUNT(*) AS total FROM ipam_subnets]
Table 'zabbix.ipam_subnets' doesn't exist
```

**Cause:** You enabled the module in Zabbix before running the SQL schema.

**Fix:** Run step 2 above, then reload the IPAM Pro page.

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
