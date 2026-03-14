# CSE 135 Analytics Dashboard Grader Instructions


## Login Credentials

### 'super admin'

Email: `grader@test.com`

Password: `t0n1gHtTh3mu$1c$33m$$0l0ud`

This `super admin` account gives you access to **all** pages on `reporting.cse135tirani.site`. 

As `super admin` is designed to have absolute control in the setting, it is able to access any page regardless of what's written in its `permissions` column of the `users` table. 

---

### 'analyst'

#### Full Access

Email: `analyst@test.com`

Password: `w3hu4t3@c4ot43rw1tht43th1ng$w3w@nTt0s@y`

This analyst account gives you access to **all** live backend pages. 

Save for the `Admin` tab, there should be no restrictions on any of the live backend pages. 

This page exists as a method for you to look at each live backend webpage without having the log into multiple accounts.

#### Limited Access
Since part of the assignment entails `analyst` roles being assigned to specific channels (i.e. one analyst might be responsible for performance tracking while another might be responsible for tracking the frequency of errors on the page), four accounts have been created which dive into the subject.

1. Performance Analyst
   
   Email: `performance_analyst@test.com`
   
   Password: `n3v3rg0nn@s@yG00dby3`
      - This account gives you access only to the **performance** live backend page. 
      - Any other page will return a `403` error, and specify a lack of permissions as the reasoning.

2. Errors Analyst
   
   Email: `errors_analyst@test.com`
   
   Password: `n3v3rg0nn@t311@l13&hu4ty0u`
      - This account gives you access only to the **errors** live backend page. 
      - Any other page will return a `403` error, and specify a lack of permissions as the reasoning.

3. Performance & Pageviews Analyst
   
   Email: `analyst_pageperform@test.com`
   
   Password: `w3c0u1dh@v3b33ns0g00dt0g3t4er`
   - This account gives you access to **both the performance and pageview** live backend pages.
   - Any other page will return a `403` error, and specify a lack of permissions as the reasoning. 

4. Events & Errors Analyst 
   
   Email: `analyst_eventerror@test.com`
   
   Password: `w3c0u1dh@v3l1v3dth1$d@nc3f0r3v3r`
   - This account gives you access to **both the events and errors** live backend pages.
   - Any other page will return a `403` error, and specify a lack of permissions as the reasoning. 

---

### 'viewer'

Email: `viewer@test.com`

Password: `m@yb31t$b3Tt3rth1$w@y`

Due to the nature of the `viewer` role, this login takes the user directly to the reporting page, where a summary of all the reports are found. This means that the viewer role cannot access any of the other live backend pages. If attempted, then it returns a `403` error and will redirect the user to `403.html`. Upon clicking the `Return Home` link, the viewer is taken back to `reports.php`.

---

## Working Paths + Login For Each

### Building a Report

Login Credentials can be any of the `analyst` logins listed above. For simplicity's sake, use the full-access analyst credentials.

**Step 1:** Navigate to `https://reporting.cse135tirani.site/index.html`

**Step 2:** Enter credentials
- Email: analyst@test.com
- Password: w3hu4t3@c4ot43rw1tht43th1ng$w3w@nTt0s@y

**Step 3:** Navigate to `Build Reports` on sidebar

**Step 4:** Build a Report
- Leave all sections selected:
  - performance
  - errors
  - pageviews
  - sessions
  - events
- Add comments in the `analyst comments` fields
- Click `Preview Report` &rarr; this will generate a JSON file below.

**Step 5:** Generate a Static Report
- Click **Create Static Report**
- Scroll up to the top of the page - a query will be shown starting with `/report_view?token=...`

**Step 6:** Go to `https://reporting.cse135tirani.site/report_view?token=...`
- The report will have saved there in a static HTML page.

**Step 7:** Use **Back Arrow** to go back to Dashboard and go to `Saved Reports` 
- The new report should be at the very top of the table, with the title, who created it, time it was created, listed in the row.

**Step 8:** Open the Saved Report
- Click **Open** &rarr; This opens a static report view.

---

### Testing Authentication

**Step 1:** Login as viewer
- Email: `viewer@test.com`
- Password: `m@yb31t$b3Tt3rth1$w@y`
- You will land on `https://reporting.cse135tirani.site/saved_reports.php`

**Step 2:** Attempt Forceful Browsing
1. Attempt to navigate to `https://reporting.cse135tirani.site/dashboard.php`
   - The webpage won't change &rarr; this is due to the fact that `dashboard.php` is the landing page after login. However, to avoid a continuous loop of `error 403` because `viewer` isn't authorized to access `dashboard.php`, `dashboard.php` checks the user's role and redirects any user with a `viewer` role to `saved_reports.php`.
2. Attempt to navigate to any of `https://reporting.cse135tirani.site/error-report.php`, `https://reporting.cse135tirani.site/speed-reporting.php`,`https://reporting.cse135tirani.site/events.php`, `https://reporting.cse135tirani.site/pageviews.php`, `https://reporting.cse135tirani.site/admin-report.php`
   - The webpages will send you to `403.html` where it informs you that you do not have permission to enter the page. Clicking the link on the page will take users with `super admin` or `analyst` roles back to `dashboard.php` and those with `viewer` roles to `saved_reports.php`.

**Step 3:** Logout of Viewer account.

**Step 4:** Attempt Forceful Browsing Using Links Above
  - The webpage will send you to `401.html`, where it informs you that authentication is required. Clicking the link on the page takes you back to the login screen.

---

### Testing Permissions

**Step 1:** Login as analyst_pageperform
- Email: `analyst_pageperform@test.com`
- Password: `w3c0u1dh@v3b33ns0g00dt0g3t4er`

**Step 2:** Click **Errors** on sidebar
- You will be redirected to `403.html` due to lack of permissions.
- Navigate back to `dashboard.php` by clicking the link 
- Do the same for **Events**

**Step 3:** Click on **Pageviews** or **Performance**
- You will be able to see their graphs and data.
- Navigate back to `dashboard.php` by clicking the back arrow on the browser.

**Step 4:** Click on **Admin**
- It won't redirect you to the `403.html` page. Instead, it informs you there that you don't have permission to see the users table.

---

## Bugs and Issues

1. `saved_reports.php` unavailable when Javascript is turned off &rarr; saved_reports.php currently relies on JavaScript to populate the table of saved reports.  
If JavaScript is disabled the page will not render the report list. Due to time constraints I was unable to fully refactor the page to support server-side rendering while preserving the existing filtering and modal preview features.
2. The `error_report.php` page currently has a bug where data only appears when the selected end date is in the future. This appears to be related to how the SQL query filters timestamps, but I was unable to fully debug the issue before submission.
3. Mobile Layout &rarr; While responsive styles exist, some tables may require horizontal scrolling on very small screens.


---

## Architecture Notes

The system separates responsibilities across three layers:

### Backend
PHP API endpoints provide analytics queries and report generation.

### Database
MySQL stores analytics data, users, and saved reports.

### Frontend
JavaScript renders charts and handles report previews.

Saved reports are rendered server-side to support script-off fallback.

---

## Summary

This analytics dashboard allows analysts to generate reports from site analytics data including performance, events, errors, pageviews, and sessions. Reports can be previewed, saved, and exported. The system includes role-based access control with super admin, analyst, and viewer roles.