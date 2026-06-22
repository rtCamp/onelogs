=== OneLogs ===
Contributors: rtcamp, whopiyush, patil-vipul, up1512001, justlevine, aviral-mittal, rishavjeet, vishal4669, vishalkakadiya
Donate link: https://rtcamp.com/
Tags: OneLogs, Site Logs, Site Activity, Activity Logs, Stream, Multisite, Network, OnePress, Audit, Compliance
Requires at least: 6.8
Tested up to: 6.9
Stable tag: 1.0.3
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

OneLogs extends the Stream plugin to provide a centralized, network-level dashboard for viewing and managing activity logs across WordPress Multisite or OnePress installations.

== Description ==

OneLogs extends the capabilities of the Stream plugin by providing a centralized interface for viewing and managing activity logs across multiple connected sites. Built as part of the OnePress Framework, it bridges the gap between individual site activity tracking and network-level monitoring, helping teams maintain visibility, compliance, and operational awareness across their digital ecosystem.

**Why OneLogs?**

In multisite environments or enterprise networks, managing user activity and ensuring compliance across all sites can quickly become complex. While Stream provides detailed local logging, OneLogs takes it a step further by aggregating and governing activity logs across the OnePress network for improved oversight and operational transparency.

OneLogs solves this by:

* **Centralizing Log Management:** Single dashboard to monitor activity across all brand sites
* **Stream-Powered Logging:** Built on top of the reliable Stream plugin for robust event tracking
* **Enabling Network-Wide Governance:** Distinguish between governing and brand site activity with easy filtering
* **Supporting Compliance & Audit:** Maintain complete audit trails of all site actions across users and contexts

**Key Benefits:**

* **Unified Visibility:** Monitor activity across all connected sites from a single, centralized dashboard
* **Proactive Issue Detection:** Spot unusual activity in real time and investigate directly from the governing site
* **Compliance & Audit Ready:** Maintain complete audit trails of all site actions across users and contexts
* **Time-Saving:** Eliminate the need to switch sites or export logs manually from each brand site
* **Flexible Export:** Export filtered logs to CSV for offline analysis, reporting, or archival purposes

**Core Features:**

* **Stream Integration:** Uses Stream's event logging system for reliable, detailed tracking
* **Unified Dashboard:** View and manage activity logs across all connected sites from one interface
* **Site Selector:** Filter logs by Governing Site or individual Brand Sites
* **Advanced Filtering:** Search by user, context, action, summary, or date range
* **CSV Export:** Export selected or filtered logs directly to CSV for further analysis
* **Brand-Aware Access:** Governing Site can view all logs; Brand Sites can only view their own
* **Real-Time Sync:** Updates log entries instantly as new actions are recorded on connected sites

**Perfect for:**

* Enterprise WordPress deployments with multiple sites
* Organizations requiring centralized activity monitoring
* Companies with strict compliance and audit requirements
* Agencies managing multiple client sites
* Teams needing unified visibility across WordPress networks

== Installation ==

1. Download the OneLogs plugin ZIP from the latest GitHub release and install it on your WordPress sites
2. Ensure the Stream plugin is installed and active on all sites you want to monitor
3. Activate the plugin through the 'Plugins' screen in WordPress
   * For multisite installations, make sure to Network Activate the plugin
4. **Choose Site Type (One-time selection):**
   * **Governing Site:** Central log management dashboard
   * **Brand Site:** Individual sites connected for log aggregation
5. **For Brand Sites:** Navigate to Dashboard → OneLogs → Settings to get the API key
6. **For Governing Site:** Go to Dashboard → OneLogs → Settings and register each Brand Site with its name, URL, and API key

**Important:** Stream must remain active on all sites for OneLogs to function properly.

== Frequently Asked Questions ==

= Does OneLogs replace Stream? =

No, OneLogs extends Stream to provide centralized visibility across sites. Stream must remain active on all sites for OneLogs to work.

= What filters are supported? =

You can filter logs by Site, User, Context, Action, and Date Range from the unified dashboard.

= What fields are searched? =

The search field searches within the Summary column of logged events.

= Can I export logs? =

Yes. You can export filtered or complete log datasets to CSV directly from the dashboard.

= Can Brand Sites view logs from other sites? =

No. The Governing Site can view all logs across the network, but Brand Sites can only view their own local activity logs.

= How are logs synchronized between sites? =

Logs are synchronized in real-time using secure REST API communication. Updates appear instantly as new actions are recorded on connected sites.

== Screenshots ==

@todo - Screenshots need to be captured and added before release.

== Changelog ==

See <a href="https://github.com/rtCamp/OneLogs/blob/main/CHANGELOG.md" target="_blank">CHANGELOG.md</a> for detailed changelog.

== Troubleshooting ==

**No brand site logs visible on the Governing Site**

* Ensure both Stream and OneLogs plugins are active on each Brand Site
* Verify API keys and REST endpoints are correctly configured
* Verify any customizations made to REST API permissions

**CSV export missing data**

* Try exporting without filters to confirm the data is correctly fetched
* Verify configured filters aren't too restrictive

== Support ==

For support, feature requests, and bug reports, please visit our <a href="https://github.com/rtCamp/OneLogs" target="_blank">GitHub repository</a>.

== Contributing ==

OneLogs is open source and welcomes contributions. Visit our <a href="https://github.com/rtCamp/OneLogs" target="_blank">GitHub repository</a> to contribute code, report issues, or suggest features.

Development guidelines and contributing information can be found in our repository documentation.
