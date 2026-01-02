# Cabin Analytics Dashboard Widget for WordPress

A WordPress-native dashboard widget that displays **Cabin Analytics** data directly inside `/wp-admin`, with two display modes:

- **Summary + Sparkline** (compact, lightweight)
- **Summary + Stacked Views / Visitors Chart** (Cabin-style)

The widget is visible to **all dashboard users**, while configuration is restricted to **Administrators only**.

---

## Features

- 📊 **WordPress Dashboard widget** (no front-end impact)
- 🔐 **Admin-only settings page**
- 🌐 Automatically uses the **current site domain**
- ⏱ **Date ranges:** Today · 7 days · 30 days
- ⭐ Configurable **default date range**
- 🔄 Manual refresh with nonce protection
- 🧠 Smart transient caching (10 minutes)
- 📈 Two display modes:
  - **Sparkline mode**
    - Larger, Cabin-style sparkline
    - Label above (“Trend”)
    - Description below
  - **Chart mode**
    - Full-width stacked bar chart
    - Visitors (dark base) + Views (light cap)
    - Hover tooltips (pure SVG `<title>`, zero JS)
    - Legend (“Views / Visitors”)
    - Metrics displayed below chart
- 🔗 Direct link to the Cabin web dashboard
- ♿ Accessible, semantic, and JS-minimal

---

## Requirements

- WordPress **6.8+**
- PHP **8.3+**
- A valid **Cabin Analytics API key**

---

## Installation

1. Clone or download the repository:
   ```bash
   git clone https://github.com/your-org/wp-cabin-dashboard-widget.git
   ```
2. Place the plugin folder in:
   ```
   wp-content/plugins/wp-cabin-dashboard-widget
   ```
3. Activate the plugin in **Plugins → Installed Plugins**

---

## Configuration

### 1. Add your API key
Go to:

**Settings → Cabin Analytics**

- Enter your Cabin API key
- The plugin automatically uses the site’s domain (`home_url()`)

### 2. Choose widget options
From the same settings page:

- **Widget Display**
  - Sparkline (minimal)
  - Chart (Cabin-style)
- **Default Date Range**
  - 7 days
  - 14 days
  - 30 days

---

<img width="2062" height="671" alt="image" src="https://github.com/user-attachments/assets/4da2a7f8-eb1a-461a-b522-29908fe8c87b" />


## Dashboard Usage

- The widget appears on the **WordPress Dashboard** for all users
- Users can:
  - Switch date ranges
  - Refresh data
  - View analytics (read-only)
- Only admins can:
  - Change API keys
  - Change defaults
  - Switch display modes

---

## Cabin Dashboard Link

Each widget includes a **“View Dashboard”** link that opens:

```
https://withcabin.com/dashboard/{your-domain}
```

---

## Caching

- API responses cached using WordPress transients
- Default cache duration: **10 minutes**
- Manual refresh clears cache immediately

---

## License

GPL-2.0-or-later  
© 2026 Stephen Walker

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
