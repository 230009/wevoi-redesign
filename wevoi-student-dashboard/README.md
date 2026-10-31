# WeVoi Student Dashboard

A WordPress plugin that provides a dashboard for students to view their competition registrations, results, and certificates.

## Features

- **My Registrations**: View all competition registrations with status and video links
- **Competition Results**: See competition results including medals, points, ranks, and summit coupon codes
- **My Certificates**: Access and download competition certificates
- **Supervisor Information**: View supervisor details
- **Responsive Design**: Works on desktop and mobile devices

## Installation

1. Upload the `wevoi-student-dashboard` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the shortcode `[wevoi_student_dashboard]` on any page or post

## Usage

### Shortcode

```
[wevoi_student_dashboard]
```

### Shortcode Attributes

- `show_certificates` (default: 'yes') - Show certificates tab
- `show_results` (default: 'yes') - Show results tab
- `show_registrations` (default: 'yes') - Show registrations tab

Example:
```
[wevoi_student_dashboard show_certificates="yes" show_results="yes" show_registrations="yes"]
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Student role must be assigned to users
- WeVoi Competition Manager plugin (for competition data)

## Access Control

- Only logged-in users with the 'student' role can access the dashboard
- Non-logged-in users will see a login message
- Users without the student role will see an access denied message

## File Structure

```
wevoi-student-dashboard/
├── assets/
│   ├── css/
│   │   └── student-dashboard.css
│   └── js/
│       └── student-dashboard.js
├── class-student-dashboard.php
├── class-student-shortcode.php
├── dashboard.php
├── wevoi-student-dashboard.php
└── README.md
```

## Version

1.0.0

## Author

Kenan

## License

This plugin is proprietary software.

