# CSE 135 Analytics Dashboard Project

### Student: Tia Irani (A17303637)

## Overview

This project implements a full-stack analytics reporting system with authentication, role-based authorization, report generation, visualization, and export capabilities. 

The system collects site analytics data - such as pageviews, sessions, performance metrics, events, and errors - and allows analysts to generate structured reports containing charts, tables, and commentary. Reports are saved, shared, and exported for later viewing.

The system is deployed as a PHP + JavaScript web application backed by a MySQL database.

---

## Live Deployment

Production Site:

[https://reporting.cse135tirani.site](https://reporting.cse135tirani.site)

Main Pages:

1. Login &rarr; [https://reporting.cse135tirani.site/index.html](https://reporting.cse135tirani.site/index.html)
2. Report Builder &rarr; [https://reporting.cse135tirani.site/reports.php](https://reporting.cse135tirani.site/reports.php)
3. Saved Reports &rarr; [https://reporting.cse135tirani.site/saved_reports.php](https://reporting.cse135tirani.site/saved_reports.php)
4. Dashboard &rarr; [https://reporting.cse135tirani.site/dashboard.php](https://reporting.cse135tirani.site/dashboard.php)


## Repository

Github Repository: 

[https://github.com/tirani427/cse135_homework_repository_tirani](https://github.com/tirani427/cse135_homework_repository_tirani)

## System Features

This system supports three roles:
### `super admin`
- Manages users
- Access to all analytics pages
- Generate Reports
- Save Reports
- View Saved Reports

### `analyst`
- Access analytics dashboards based on variety of **permissions**
- Generate reports
- Save reports
- View Saved Reports

### `viewer`
- Cannot access analytics dashboards
- Can only view previously saved reports

Access control is enforced through PHP session checks and role verification.

---

## Reporting System

Reports can include multiple sections from the following list:
- Performance
- Pageviews
- Errors
- Sessions
- Events

Each section can include three kinds of data visualization:
- charts
- summary tables
- analyst commentary

Reports are generated from selected date ranges and chosen sections.

---

## Report Builder

The report builder allows analysts to:
- choose report sections
- define a date range
- add analyst commentary
- preview report output
- export reports

This allows for the curation of specified reports based on the analyst's needs.

There are two ways that a report can be exported:

1. Preview &rarr; Interactive preview inside the dashboard
2. Static HTML Report &rarr; Saved reports are stored in the database and accessible via share tokens and urls.

---

## Saved Reports

Saved reports provide report history, shareable report URLs, and static report rendering. 

Viewer accounts can only access these static saved reports.

Saved reports can be accessed from [https://reporting.cse135tirani.site/saved_reports.php]([https://reporting.cse135tirani.site/saved_reports.php).

---

## Data Visualization

Charts are implemented using Chart.js. Some charts used for this project include:
- line charts for time-series trends
- bar charts for top events or errors
- summary statistic cards
- data tables for detailed breakdowns

---

## Error Handling

To ensure a smooth user experience, several safety mechanisms are implemented.

1. `400.html`, `401.html`, `403.html`, `404.html`, `500.html`

These pages serve as endpoints for when the API returns status codes other than `200` OK.

   1. `400.html`
      - In the instance that the formatting, parameters, or requirements for certain requests aren't fulfilled, the API will redirect the user to this page, which then takes them back to the `dashboard.php` or `saved_reports.php` (depending on their role as `super admin`/`analyst` or `viewer`.)
   2. `401.html`
      - In the instance that an unregistered `user` tries to access any page, they get redirected to `401.html` then taken back to `index.html` to login. 
      - Example:
        - Forceful browsing with `https://reporting.cse135tirani.site/error-report.php` while not logged in.
        - `error-report.php` checks `isset($_SESSION['user])`
        - `isset($_SESSIONS['user'])` returns false
        - `header('Location: /401.html)` is set
        - `error-report.php` exits

   3. `403.html` 
      - In the instance that a user tries to access page X and lacks the necessary roles/permissions, the API returns `error 403`. 
      - Example:
        - `analyst_pageperform` attempts to access `Errors` live backend page. 
          - `error-report.php` sends `GET` request to `/api/index.php/errors`
            - `/api/index.php/errors` runs `requireAuth()` to check if `user` exists.
            - `/api/index.php/errors` runs `requirePermissions(['super admin', 'analyst'], ['errors'])` to check `user` permissions.
            - `$permissions = get_permissions()` returns `['reporting', 'pageviews', 'performance]`
              - `requirePermissions($allowedRoles, $required)` checks `$permissions` against `$required` &rarr; returns error `403`.
          - `error-report.php` receives `resp.status 403` 
          - `error-report.php` redirects to `/403.html`
   4. `404.html`
      - In the instance that a page cannot be found, the page redirects to `404.html`
   5. `500.html`
      - In the instance that a server-side error is returned, the user is redirected to `500.html`, which will prompt them to return to the dashboard.

---

## Script Off Handling

The application includes graceful fallback behavior when JavaScript is disabled.

For most of the pages, a popup will appear when JavaScript is disabled. This popup advises the user to reenable JavaScript, and is mainly visible on the live backend pages.

Saved reports currently rely on JavaScript to fetch report metadata and populate the table of reports dynamically. It remains unavailable when JavaScript is turned off. Time constraints led to an inability to properly test functionality for script off handling, which resulted in it being left out at this point in time. 

---

## AI Usage

AI tools (ChatGPT) were used during development primarily for debugging assistance and architecture discussions. Due to the complexity of the project, AI was used to understand reasoning behind API endpoint errors and proper set-up of tables in the MySQL database. That being said, AI was **not used** as a direct code generator and only used for debugging aide. 

The value of AI was significant, particularly in accelerating debugging as errors in the database were often difficult to pin down.

---

## Future Improvements

If more time were available, the following improvements would be implemented:

### Server-side rendering for Saved Reports
Currently the `saved_reports` page relies on JavaScript to populate the table of reports.  
A future improvement would move this logic to server-side rendering in PHP, so the page works fully when JavaScript is disabled.

### Improved PDF Export
Currently, saved reports are viewed through URLs stored in `save_reports.php`. If given more time, further expansion into downloadable PDFs through the system would be implemented.

### Inclusion of Charts
At the moment, the summarized data is stored in tables. If given the proper amount of time to implement, including charts into the reports would be next.

### More Analytics Metrics
With the proper amount of time, more behavioral analytics metrics would employed in data collection.

---

## Technologies Used

Backend
- PHP
- MySQL
- Apache

Frontend
- HTML
- CSS
- JavaScript
- Chart.js

Infrastructure
- DigitalOcean
- Apache via virtual hosts
- HTTPS via Certbot

---

## System Architecture

The system follows a simple three-layer architecture:

Frontend  
HTML, CSS, and JavaScript render dashboards and visualizations using Chart.js.

Backend  
PHP endpoints handle authentication, authorization, analytics queries, and report generation.

Database  
MySQL stores analytics events, user accounts, and saved report metadata.

---

## Conclusion
This project demonstrates a full-stack analytics reporting platform that combines authentication, role-based access control, data visualization, and report generation to support data-driven analysis of website activity.