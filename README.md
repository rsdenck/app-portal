# Portal - Infrastructure & Security Management

Portal is a high-performance datacenter management system focused on virtualization (vCenter), software-defined networking (NSX), and advanced security correlation.

## 🚀 Quick Start

To install the portal on a fresh RHEL or Debian server, run:

```bash
git clone <repository-url>
cd portal
chmod +x scripts/install/install.sh
sudo ./scripts/install/install.sh
```

For detailed instructions, see [INSTALL.md](INSTALL.md).

## 🏗 Architecture

The system follows a modular architecture with background collectors to ensure high performance and low latency for the user interface.

### Background Collectors (Systemd)
- **vCenter Collector**: Syncs inventory and stats.
- **NSX Collector**: Maps SDN topology and flows.
- **BGP Collector**: Monitors network peering and routes.
- **Threat Intel**: Correlates data from AbuseIPDB, Shodan, and others.

## 🛠 Features & Integrations

- **Virtualization**: Full VMware vCenter integration.
- **SDN**: NSX-T/V integration for segments, gateways, and security.
- **Security Correlation**: 
    - **AbuseIPDB**: IP reputation.
    - **Shodan**: Attack surface mapping.
    - **Nuclei/Wazuh**: Vulnerability and event correlation.
    - **FortiAnalyzer**: Log and incident analysis.
- **Network**: BGP Peering, SNMP monitoring, and Netflow/IPFlow analysis.
- **Maps**: Real-time interactive map with threat visualization.

## 🔐 Security

- **RBAC**: Role-Based Access Control integrated with Category Access (RBCA).
- **Audit Logs**: Full tracking of user actions.
- **Data Protection**: Secrets should be managed via environment variables or the secure config system.

> [!WARNING]
> Never commit real API keys or credentials to the repository. Use `.env` files or the database configuration plugin.

## 🤝 Contributing

1. Create a branch: `git checkout -b feature/new-feature`
2. Commit changes: `git commit -m "feat: add new feature"`
3. Push to branch: `git push origin feature/new-feature`
4. Open a Pull Request.

---
*Developed by Portal Dev Team*
