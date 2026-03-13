# CSE 135 Project GRADER.md File

## Login Credentials

### 'super admin'

Email: `grader@test.com`

Password: `t0n1gHtTh3mu$1c$33m$$0l0ud`

This `super admin` account gives you access to **all** pages on `reporting.cse135tirani.site`. As `super admin` is designed to have absolute control in the setting, it is able to access any page regardless of what's written in its `permissions` column of the `users` table. 

### 'analyst'

#### Full Access

Email: `analyst@test.com`

Password: `w3hu4t3@c4ot43rw1tht43th1ng$w3w@nTt0s@y`

This analyst account gives you access to **all** live backend pages. Save for the `Admin` tab, there should be no restrictions on any of the live backend pages. This page exists as a method for you to look at each live backend webpage without having the log into multiple accounts.

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

### 'viewer'

Email: `viewer@test.com`

Password: `m@yb31t$b3Tt3rth1$w@y`

Due to the nature of the `viewer` role, this login takes the user directly to the reporting page, where a summary of all the reports are found. This means that the viewer role cannot access any of the other live backend pages. If attempted, then it returns a `403` error and will redirect the user to `403.html`. Upon clicking the `Return Home` link, the viewer is taken back to `reports.php`.


## Working Paths + Login For Each

## Bugs and Issues