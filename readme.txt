=== OctaHexa Server Monitor ===
Contributors: brianchin
Tags: server monitor, system resources, cpu usage, memory monitor, performance
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor server resources like CPU, memory, load average, and MySQL performance with real-time dashboards and email alerts.

== Description ==

OctaHexa Server Monitor provides comprehensive server resource monitoring directly from your WordPress admin panel. Similar to tools like htop, top, and glances, this plugin gives you real-time insights into your server's performance.

= Key Features =

* **Real-Time Monitoring** - View current CPU, memory, load average, and disk usage
* **Email Alerts** - Get notified when resources exceed configurable thresholds
* **MySQL Monitoring** - Track MySQL CPU usage and slow queries
* **CPU Steal Detection** - Identify virtualization performance issues
* **Historical Data** - View trends with beautiful charts
* **Automatic Recovery Alerts** - Know when your server returns to normal
* **Configurable Retention** - Keep logs from 1 to 90 days

= Perfect For =

* System administrators managing WordPress sites
* Developers needing performance insights
* Site owners wanting to prevent downtime
* Anyone running resource-intensive plugins

= Technical Details =

The plugin uses native PHP functions and WordPress APIs to gather system information without requiring external dependencies. All data is stored locally in your WordPress database.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Navigate to 'Server Monitor' in your admin menu
4. Configure alert thresholds in the Settings page

= Minimum Requirements =

* WordPress 5.8 or greater
* PHP version 7.4 or greater
* Linux-based hosting (for full functionality)

== Frequently Asked Questions ==

= Will this work on shared hosting? =

The plugin will work on most hosting environments, but some shared hosts may restrict access to system information. You'll get the most accurate data on VPS or dedicated servers.

= How often does it check resources? =

By default, the plugin checks resources every minute. This is configurable and uses WordPress cron for efficient scheduling.

= Will this slow down my site? =

No. The plugin uses WordPress cron to run checks in the background and has minimal performance impact.

= Can I customize alert thresholds? =

Yes! All thresholds are configurable:
- CPU usage percentage
- Memory usage percentage  
- Load average (auto-calculated based on CPU cores)
- MySQL CPU threshold
- Slow query count

= What information is included in alert emails? =

Alert emails include:
- Specific metrics that triggered the alert
- Current values vs. thresholds
- Server name and timestamp
- Direct link to the admin dashboard

= Does it send data to external servers? =

No. All monitoring and data storage happens locally on your server. No information is sent externally.

== Screenshots ==

1. Real-time dashboard showing server resources
2. Email alert configuration settings
3. Historical data charts
4. Detailed logs view
5. Sample alert email

== Changelog ==

= 1.0.0 =
* Initial release
* Real-time CPU, memory, and load monitoring
* MySQL performance tracking
* Email alert system
* Historical data logging
* Responsive admin interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of OctaHexa Server Monitor.

== Privacy Policy ==

This plugin does not:
- Send any data to external servers
- Track users
- Use cookies
- Collect personal information

All server metrics are stored locally in your WordPress database and are only accessible to administrators.

== Support ==

For support, please visit: https://octahexa.com/support

== System Requirements ==

While the plugin will install on any WordPress site meeting the minimum requirements, full functionality requires:

* Linux-based hosting
* Access to /proc filesystem  
* PHP sys_getloadavg() function
* Ability to query MySQL process list

The plugin will gracefully degrade on systems without these capabilities.
