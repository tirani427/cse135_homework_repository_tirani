# cse135_homework_repository_tirani

## Group Members
Tia Irani

## Grader Password
`n3v3rg0nnag1v3y0uUp`

## Homework 1

### Github Deploy Set Up
1. Getting DocumentRoot from server:
   - From previous steps, I knew the docroot for the server would be located at `/var/www/cse135tirani.site/public_html`.
2. Local Repository Set Up
   - Website files were copied from server's Apache DocumentRoot to local Git repository.
3. SSH Access for Development
   - SSH key was created specifically for Github Actions
     - Public Key added to DigitialOcean server under user `tiairani`
     - Private key stored securely as repository secret
4. Github Actions Secret
   - In order to use the workflow I wanted to, I set up 4 repository secrets:
     1. `DO_HOST` &rarr; contained the IP address for DigitalOcean droplet
     2. `DO_USER` &rarr; SSH user on droplet
     3. `DO_PATH` &rarr; contained the DocumentRoot
     4. `DO_SSH_KEY` &rarr; contained private ssh key for deployment 
5. Github Actions Workflow
   - Workflow file was added at .github/workflows/deploy.yml
     - Workflow:
       - Runs on every push to `main`
       - Checks out the repository
       - Sets up SSH with stored private key
       - Uses `rsync` to sync files to Apache DocumentRoot
6. Server Permissions
   - In order to avoid complications, user `tiairani` was granted write-access to the DocumentRoot.
   - Apache retains read-access.
  
### Github Deploy Flow
Edit File Locally 

&darr;

git commit

&darr;

git push origin main

&darr;

Github Actions workflow runs

&darr;

Files synchronized to DigitalOcean via `rsync`

&darr;

Changes appear live on website

### Login / Password Protection
User: tiairani, Password:  n3v3rg0nN@l3ty0ud0wn
User: grader, Password: n3v3rg0nN@tUrN@r0unD&d3s3rTy0u

### Compression 
Completing this part of the assignment involved enabling Apache's `mod_deflate` and configuring it to gzip-compress text-based responses. Once compression was enabled and Apache reloaded, Chrome DevTools Network showed that the responses returned with `Content-Encoding: gzip`. The "Size" field also indicated a smaller transferred payload compared to resource size - which confirms the browser received the compressed version and decompressed it automatically for rendering. 

### Obscure Server Identity
In order to accomplish this part of the assignment, I used ServerToken, ServerSignature, and SecServerSignature, the latter coming from installing Apache's mod-security2. My first strategy was setting ServerToken Prod and ServerSignature Off, and having the condition that `Header always Set Server "CSE135 Server`. However, this only resulted in `Server: Apache` - shorter than before but not changed. So I did more research into methods of hiding the server identity and found mod-security2, which discussed setting the new server name with SecServerSignature. One source recommended setting `ServerTokens Full` so I changed my implementation to attempt it that way. I included `SecServerSignature "CSE135 Server"` in the security.conf file, which worked in the end to hide the server identity.
