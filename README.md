# Maintenance Widget (Monzphere)

## Overview
Compact dashboard widget for visualizing maintenance periods in Zabbix. It is designed to match the native UI style and stay readable in both Blue and Dark themes.


## Requirements
- Zabbix frontend with dashboard widget module support enabled
- Permission to access Maintenance configuration (`UI_CONFIGURATION_MAINTENANCE`)

## Installation
1. Copy the module to `/usr/share/zabbix/modules/MaintenanceWidget`.
2. Enable the module in **Administration → General → Modules**.
3. Add **Maintenance Overview** widget to a dashboard.

## Widget configuration
- **State**: Any / Active / Approaching / Expired
- **Limit**: Maximum number of entries to show
- **Show scope**: Display hosts and host groups
- **Show description**: Show tooltip icon for descriptions
- **Time period**: Uses the dashboard time selector by default

## Behavior details
- The widget shows maintenances that **overlap** the selected time period.
- Clicking a maintenance name opens `maintenance.list` filtered by name and status.
- "Open maintenance list" clears any previously applied filters.
- Scope listing shows the first 3 names inline and provides a native "more" icon with a hint
  containing up to 30 items.

## User preferences (CProfile)
- `mnz.maintenance.widget.mode`
- `mnz.maintenance.widget.limit`
- `mnz.maintenance.widget.show_scope`
- `mnz.maintenance.widget.show_description`
- `mnz.maintenance.widget.last_view`

## Structure
- `actions/WidgetView.php`
- `includes/WidgetForm.php`
- `views/widget.view.php`
- `views/widget.edit.php`
- `assets/js/class.widget.js`
- `manifest.json`
- `Widget.php`

## Development notes
- Uses native Zabbix components and icons for theme compatibility.
