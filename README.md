# OctaHexa Server Monitor

**Version:** 1.0.0  
**Author:** Brian Chin  
**Requires:** WordPress 5.8+, PHP 7.4+  
**License:** GPL v2 or later

## Description

OctaHexa Server Monitor is a comprehensive WordPress plugin that monitors server resources in real-time, similar to htop, top, and glances. It provides visual dashboards, email alerts, and detailed logging for system administrators.

## Features

### Real-Time Monitoring
- **CPU Usage**: Monitor CPU utilization with multi-core support
- **Memory Usage**: Track RAM usage and available memory
- **Load Average**: Display 1, 5, and 15-minute load averages
- **CPU Steal**: Detect virtualization issues
- **MySQL Monitoring**: Track MySQL CPU usage and slow queries
- **Disk Usage**: Monitor available disk space

### Alert System
- Configurable thresholds for all metrics
- Email notifications when thresholds are exceeded
- Recovery notifications when resources return to normal
- Alert cooldown to prevent spam
- Intelligent threshold defaults based on server configuration

### Data Logging
- Store historical data in custom database table
- Configurable retention period (1-90 days)
- Automatic cleanup of old logs
- Visual charts showing 24-hour history

### Admin Interface
- Real-time dashboard with auto-updating stats
- Color-coded status indicators (normal, warning, critical)
- Interactive charts using Chart.js
- Responsive design for mobile devices
- Detailed logs view with filtering

## Installation

1. Upload the `oh-server-monitor` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Server Monitor' in the admin menu
4. Configure thresholds in Settings

## File Structure

```
oh-server-monitor/
├── oh-server-monitor.php    # Main plugin file
├── assets/
│   ├── js/
│   │   └── admin.js         # Admin JavaScript
│   └── css/
│       └── admin.css        # Admin styles
└── readme.txt               # WordPress.org readme
```

## Configuration

### Thresholds
- **CPU Threshold**: Default 80%
- **Memory Threshold**: Default 85%
- **Load Threshold**: Auto-calculated (2x CPU cores)
- **MySQL CPU Threshold**: Default 50
- **Slow Query Threshold**: Default 10 queries

### Email Settings
- Enable/disable notifications
- Custom notification email
- Alert cooldown period

### Data Retention
- Configurable from 1 to 90 days
- Automatic daily cleanup

## WordPress Plugin Directory Compliance

### ✅ **This plugin IS allowed** in the WordPress Plugin Directory

The plugin complies with all WordPress.org guidelines:

1. **GPL License**: Uses GPL v2 or later
2. **No External Dependencies**: All functionality is self-contained
3. **Security**: Proper nonce verification, capability checks, and data sanitization
4. **Database**: Uses WordPress database API and proper table prefixes
5. **No Phone Home**: No data is sent to external servers
6. **Admin Only**: Resource monitoring is restricted to administrators
7. **Performance**: Uses WordPress cron for scheduled tasks
8. **Coding Standards**: Follows WordPress coding standards

### Potential Considerations

While the plugin is compliant, reviewers might note:

1. **Server Requirements**: Some features require Linux `/proc` filesystem
2. **Hosting Compatibility**: Shared hosting may restrict access to system stats
3. **Performance Impact**: Minimal, uses efficient cron scheduling
4. **Database Usage**: Creates one custom table for logging

### Review Tips

To ensure smooth approval:
- Document hosting requirements clearly
- Provide graceful fallbacks for restricted environments
- Include uninstall routine to clean up database
- Test on various hosting environments

## System Requirements

### Minimum Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Linux-based hosting (for full functionality)

### Recommended
- Dedicated or VPS hosting
- Access to `/proc` filesystem
- `sys_getloadavg()` function enabled
- Sufficient database storage for logs

## Hooks and Filters

### Actions
- `oh_server_monitor_check` - Runs resource check
- `oh_server_monitor_cleanup` - Runs log cleanup

### Filters
- `oh_server_monitor_thresholds` - Modify alert thresholds
- `oh_server_monitor_alert_message` - Customize alert emails

## Changelog

### Version 1.0.0
- Initial release
- Core monitoring functionality
- Email alerts
- Admin dashboard
- Historical logging

## Support

For support, feature requests, or bug reports, please visit:
https://octahexa.com/support

## Credits

- Chart.js for data visualization
- WordPress Plugin Boilerplate for structure inspiration

---

**Suggested Commit Message:**
```
Initial commit: OctaHexa Server Monitor v1.0.0

- Add core server monitoring functionality
- Implement real-time dashboard with Chart.js
- Add email alert system with thresholds
- Create historical logging with configurable retention
- Include CPU, memory, load, MySQL, and disk monitoring
- Add CPU steal detection for virtualized environments
- Implement responsive admin interface
- Follow WordPress coding standards and best practices
```
