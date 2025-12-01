![Banner](https://rtcamp.com/wp-content/uploads/sites/2/2024/09/OneLogs-Banner.png)

# OneLogs

Contributors: [rtcamp](https://profiles.wordpress.org/rtcamp), [whopiyush](https://github.com/whopiyush), [patil-vipul](https://github.com/patil-vipul), [up1512001](https://github.com/up1512001), [justlevine](https://github.com/justlevine), [aviral-mittal](https://github.com/aviral-mittal), [rishavjeet](https://github.com/rishavjeet), [vishal4669](https://github.com/vishal4669), [vishalkakadiya](https://github.com/vishalkakadiya)

Tags: OneLogs, Site Logs, Logs Sync, Site Auditing, Records, WordPress network, Site Activity, Activity management,
WordPress plugin, Debug, Compliance

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](http://www.gnu.org/licenses/gpl-2.0.html)

This tool enables unified activity log visibility and governance monitoring across all sites in a WordPress multisite or
OnePress network.

## Description

OneLogs extends the capabilities of the [Stream](https://wordpress.org/plugins/stream/) plugin by providing a
centralized interface for viewing and managing logs across multiple connected sites.
Built as part of the OnePress Framework, OneLogs bridges the gap between individual site activity tracking and
network-level monitoring. Helping teams maintain visibility, compliance, and operational awareness across their digital
ecosystem.

## Why OneLogs?

In multi-site environments or enterprise networks, managing user activity and ensuring compliance across all sites can
quickly become complex.

While Stream provides detailed local logging, OneLogs takes it a step further, aggregating and governing activity logs
across the OnePress network for improved oversight and operational transparency.

### Benefits

- **Unified Visibility**: Monitor activity across all connected sites from a single, centralized dashboard.

- **Proactive Issue Detection**: Spot unusual activity in real time and investigate directly from the governing site.

- **Compliance & Audit**: Maintain complete audit trails of all site actions across users and contexts.

- **Stream-Powered Logging**: Built on top of the reliable Stream plugin, ensuring robust and proven event tracking.

- **Network-Wide Governance**: Distinguish between governing and brand site activity with easy site-based filtering.

- **Time-Saving**: Eliminate the need to switch sites or export logs manually from each brand site.

- **Flexible Export**: Export filtered logs to CSV for offline analysis, reporting, or archival purposes.

### Key Features

- **Stream Integration**: Uses Stream’s event logging system for reliable, detailed tracking.

- **Unified Dashboard**: View and manage activity logs across all connected sites from one interface.

- **Site Selector**: Filter logs by Governing Site or individual Brand Sites.

- **Advanced Filtering**: Search by user, context, action, summary or date.

- **CSV Export**: Export selected or filtered logs directly to CSV for further analysis.

- **Brand-Aware Access**: Governing Site can view all logs; Brand Sites can only view their own.

- **Real-Time Sync**: Updates log entries instantly as new actions are recorded on connected sites.

## Requirements

| Requirement   | Version                                                                                   |
|---------------|-------------------------------------------------------------------------------------------|
| WordPress     | >= 6.8                                                                                    |
| PHP           | >= 8.0                                                                                    |
| Prerequisites | [Stream](https://wordpress.org/plugins/stream/) plugin installed and active on all sites. |

## Installation

1. Download the OneLogs plugin ZIP from Releases of GitHub Repository.
2. Upload the `OneLogs` directory to the `/wp-content/plugins/` directory
3. Activate the [Stream](https://wordpress.org/plugins/stream/) plugin if not already active before activating OneLogs
4. For multisite installations, network activate the plugin through the ‘Plugins’ menu in WordPress
5. For single site installations, activate the plugin through the ‘Plugins’ menu in WordPress

## How It Works

### Setting Up Governing and Brand Sites

1. Install and activate the Stream and OneLogs plugins on all sites.
2. From the OneLogs settings or the popup after plugin activation, designate one site as the "Governing Site" (the
   central log view).
3. Designate all other sites as “Brand Sites” (connected shared sites).
4. Copy the API keys generated for each Brand Site from their respective settings pages
5. In the Governing Site settings, register each Brand Site by adding:

- Site name
- URL
- Logo
- API key

### Accessing the Logs Dashboard

1. On your Governing Site, open `OneLogs > Logs` from the sidebar.
2. Use the Site Selector dropdown to switch between:

- Governing Site logs
- Any connected Brand Site logs

3. On the Governing site, Governing site will be selected by default, on the Brand sites, the respective Brand site will
   be selected by default.
4. Use the Search field to find events by summary text.
5. Apply filters for Context, Action, Date, or User to narrow results.

### On a Brand Site

- The OneLogs Dashboard shows only the local Stream activity logs.
- Filtering, searching, and CSV export work the same way.
- Brand Sites cannot view or fetch logs from other sites.

## Development & Contributing

[OneLogs](https://github.com/rtCamp/OneLogs-internal) is under active development and maintained
by [rtCamp](https://rtcamp.com/).

Contributions are **Welcome** and **encouraged!** To learn more about contributing to OneLogs, please read
the [Contributing Guide](./docs/CONTRIBUTING.md).

For development guidelines, please refer to our [Development Guide](./docs/DEVELOPMENT.md).

## Frequently Asked Questions

### Does OneLogs replace Stream?

No. OneLogs extends Stream. You must have Stream active for OneLogs to work. OneLogs builds on Stream’s logging
foundation to provide centralized visibility across sites.

### Can I filter logs by site, user, or context?

Yes. The dashboard provides comprehensive filtering by Site, User, Context, Action and Date Range.

### Can I export logs?

Yes. You can export filtered or complete log datasets to CSV directly from the dashboard.

### Troubleshooting

1. **No brand site logs visible on the Governing Site**

- Ensure each Brand Site has OneLogs and Stream installed and active.
- Verify API keys and REST endpoints are correctly configured.
- Verify REST API permissions

2. **Search not working correctly**

- The search functionality only searches within the summary column

3. **CSV export empty**

- Make sure filters aren’t too restrictive.
- Try exporting without filters to test data presence.

This plugin is licensed under the GPL v2 or later.

## Get Involved

You can join the development and discussions on [GitHub](https://github.com/rtCamp/OneLogs-internal). Feel free to
report issues, suggest features, or contribute code.
