# One-time server setup

Everything you do by hand, once, on the Ubuntu 24.04 VPS. After this, every push to `main` deploys by itself
(see [Docker and deployment](../README.md#docker-and-deployment)).

Replace nothing below except the values marked `<...>`. The domain is `shop.younesghalloum.dev`, the app folder is
`/opt/baskit` and the app listens on `127.0.0.1:8081`.

```mermaid
flowchart LR
    B[Browser] -->|HTTPS 443| H[Host Nginx + Let's Encrypt]
    H -->|HTTP 127.0.0.1:8081| W[web container: Nginx]
    W -->|FastCGI| A[app container: PHP-FPM]
    A --> D[(db container: PostgreSQL)]
    Q[queue and scheduler containers] --> D
```

## 0. First connection and a new password

OVH e-mails you a first password for the `ubuntu` user (`debian` on a Debian image). Use it once, then replace it.

Generate a strong new password **on your own computer** (PowerShell) and save it in a password manager right away.
Never paste it in a chat or commit it:

```powershell
-join ((48..57)+(65..90)+(97..122) | Get-Random -Count 24 | ForEach-Object {[char]$_})
```

Connect with `-v` so you can see why if it fails:

```powershell
ssh -v ubuntu@<IPv4>
```

1. On the first connection SSH shows the server's fingerprint: type `yes`.
2. When asked, type the password from the OVH e-mail. Nothing shows on screen while you type; that is normal.
3. The server may force you to change it at once. If not, change it yourself with `passwd`: enter the e-mail password
   as the current one, then the new generated password twice.

When you see a prompt like `ubuntu@vps-xxxx:~$`, you are connected. If the connection closes before the password
prompt, the VPS may still be installing (check its status in the OVH Manager) or the username is wrong; the `-v`
output says which.

Do not enable the `root` account: the default user already has `sudo`, and a root login is what attackers try
first. Check that `sudo` works (it must print `root`), then bring the system up to date:

```bash
sudo whoami
sudo apt-get update && sudo apt-get upgrade -y
```

If the upgrade says a reboot is required, run `sudo reboot` and reconnect after a minute.

### 0.1 Log in with your own key

Install your personal public key, so you stop typing the server password. From PowerShell on your computer:

```powershell
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh ubuntu@<IPv4> "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

From now on SSH asks for the **passphrase of your key** (the one you chose when you created it), not the server
password. To type it only once per session, start the Windows SSH agent (PowerShell as Administrator, once):

```powershell
Get-Service ssh-agent | Set-Service -StartupType Automatic
Start-Service ssh-agent
```

then, in a normal PowerShell: `ssh-add $env:USERPROFILE\.ssh\id_ed25519`.

Test it in a **second** terminal before going on, and keep the first one open.

### 0.2 Change the SSH port (52222)

Bots scan port 22 first. A different port (between 49152 and 65535) is a small extra layer; it does not replace key
login and a firewall. On Ubuntu 24.04, SSH is started by a systemd *socket* (`ssh.socket`), so editing
`/etc/ssh/sshd_config` is not enough: the socket decides which ports are open.

```bash
sudo systemctl edit ssh.socket
```

Paste this in the editor, then save and exit (`Ctrl + X`, `Y`, `Enter`):

```
[Socket]
ListenStream=
BindIPv6Only=ipv6-only
ListenStream=0.0.0.0:22
ListenStream=[::]:22
ListenStream=0.0.0.0:52222
ListenStream=[::]:52222
```

Write the IPv4 (`0.0.0.0`) and IPv6 (`[::]`) addresses explicitly. With only `ListenStream=52222`, the port listens on
IPv6 alone and your computer, which connects over IPv4, gets `Connection refused`. Port 22 stays open for now so you
cannot lock yourself out.

```bash
sudo systemctl daemon-reload
sudo systemctl restart ssh.socket
sudo ss -tlnp | grep ssh        # four lines: 0.0.0.0:22, [::]:22, 0.0.0.0:52222, [::]:52222
```

Test from a second terminal: `ssh -p 52222 ubuntu@<IPv4>`. If it fails, the first session is still open to fix it
(and the OVH Manager has a KVM console as a last resort).

### 0.3 Firewall

Allow the ports first, then switch it on, so enabling it cannot cut you off:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 52222/tcp
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status numbered
```

Check from a third terminal that `ssh -p 52222 ubuntu@<IPv4>` still works. Port 22 stays allowed until the deploy
workflow connects on 52222 (step 10). Then close it:

```bash
sudo ufw delete allow 22/tcp
sudo systemctl edit ssh.socket   # delete the two ":22" lines, keep the 52222 ones
sudo systemctl daemon-reload && sudo systemctl restart ssh.socket
```

Ports 80 and 443 answer only once Nginx is installed (step 6).

## 1. Install Docker Engine and the Compose plugin

From Docker's official apt repository (as a sudo user):

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
```

```bash
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

```bash
sudo docker run --rm hello-world
docker compose version
```

## 2. Create the deploy user

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
```

> **Security note:** membership of the `docker` group is **root-equivalent** on this machine: anyone who can run
> `docker` can mount the host's filesystem into a container. That is why `deploy` has no password and only logs in
> with an SSH key, and why the key should be used for nothing else. If other apps share this VPS, consider a
> rootless Docker setup for this user instead.

Create an SSH key pair **on your own computer** (not on the server), dedicated to this deployment. Do not reuse your
personal key: the private half goes into a GitHub secret. Create it in your `.ssh` folder, never inside the project:

```bash
ssh-keygen -t ed25519 -f baskit-deploy -C "baskit github actions" -N ""
```

On Windows PowerShell use `ssh-keygen -t ed25519 -f $env:USERPROFILE\.ssh\baskit-deploy -C "baskit github actions" -N '""'`.

Install the public key on the server:

```bash
sudo install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
echo "<contents of baskit-deploy.pub>" | sudo tee -a /home/deploy/.ssh/authorized_keys
sudo chown deploy:deploy /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

## 3. Create the app folder

```bash
sudo install -d -m 750 -o deploy -g deploy /opt/baskit
```

From here on, work as `deploy` (`sudo -iu deploy`).

## 4. Let the server pull the image from GHCR

If you make the package **public** (GitHub > your profile > Packages > baskit > Package settings), skip this step.

If it stays **private**, create a classic personal access token with only the `read:packages` scope, then:

```bash
echo "<token>" | docker login ghcr.io -u <your-github-user> --password-stdin
```

Docker stores the credential in `~/.docker/config.json` of the `deploy` user.

## 5. Put the config on the server

The workflow copies `compose.yaml`, `deploy.sh` and `docker/nginx/default.conf` on every deploy, so you only need to
create the `.env`:

```bash
cd /opt/baskit
nano .env        # paste the contents of .env.production.example from the repository
chmod 600 .env
```

Fill in every `CHANGE_ME`:

- `APP_KEY`: generate one on your own computer with `php artisan key:generate --show`.
- `DB_PASSWORD`: a long random password (for example `openssl rand -base64 24`). Avoid `#`, `$` and spaces.
- `STRIPE_KEY`, `STRIPE_SECRET`: **test** keys (`pk_test_...`, `sk_test_...`).
- `STRIPE_WEBHOOK_SECRET`: see step 8.
- `MAIL_PASSWORD`: your Resend API key, see step 9.
- `TRUSTED_PROXIES=*` stays as is: it makes Laravel trust the `X-Forwarded-Proto` header so URLs and secure cookies
  use `https`.

## 6. Host Nginx: proxy the domain to the app

Point the DNS `A` record of `shop.younesghalloum.dev` to the VPS first. Then, as a sudo user:

```bash
sudo tee /etc/nginx/sites-available/shop.younesghalloum.dev > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name shop.younesghalloum.dev;

    client_max_body_size 10m;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_read_timeout 60s;
    }
}
EOF
sudo ln -s /etc/nginx/sites-available/shop.younesghalloum.dev /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 7. HTTPS with Certbot

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d shop.younesghalloum.dev --redirect -m <your-email> --agree-tos --no-eff-email
```

Certbot rewrites the server block above to listen on 443 and installs a renewal timer. Check it with
`sudo certbot renew --dry-run`.

## 8. Stripe webhook (test mode)

In the Stripe dashboard (test mode) > Developers > Webhooks, add the endpoint
`https://shop.younesghalloum.dev/stripe/webhook` with the events `payment_intent.succeeded` and `charge.refunded`.
Copy its signing secret (`whsec_...`) into `STRIPE_WEBHOOK_SECRET` in `/opt/baskit/.env`. Containers read the
`.env` at start, so after changing it run `docker compose up -d` in `/opt/baskit`.

## 9. Send e-mail with Resend

The shop sends order confirmation, shipped and refunded e-mails through the `queue` container. We use
[Resend](https://resend.com) over plain SMTP: no extra package, and the settings live only in the server's `.env`.

1. Create a Resend account, then Domains > Add Domain > `younesghalloum.dev`.
2. Add the DNS records Resend shows you (SPF, DKIM, and optionally DMARC) at your DNS provider, then click Verify.
   Verification can take a few minutes. Without it, mail is rejected or lands in spam.
3. API Keys > Create API Key, with the "Sending access" permission restricted to this domain. Copy it once.
4. In `/opt/baskit/.env`, set (the example file already has these lines):

   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.resend.com
   MAIL_PORT=587
   MAIL_USERNAME=resend
   MAIL_PASSWORD=<your re_... API key>
   MAIL_FROM_ADDRESS=hello@younesghalloum.dev
   MAIL_FROM_NAME=Baskit
   ```

5. Restart so the containers read the new `.env`: `cd /opt/baskit && docker compose up -d` (the `queue` container
   sends the mail, so it must restart too).
6. Place a test order and watch `docker compose logs -f queue`. Sent mails also appear in the Resend dashboard (Emails).

`MAIL_FROM_ADDRESS` must be on the verified domain, or Resend refuses the message. Check Resend's pricing page for the
current free-plan limits before relying on it.

## 10. GitHub: secrets and environment

In the repository: Settings > Environments > New environment, named **`production`** (optionally require a reviewer).
Add these secrets to that environment:

| Secret | Value |
|---|---|
| `SSH_PRIVATE_KEY` | contents of `baskit-deploy` (the private key from step 2) |
| `SSH_KNOWN_HOSTS` | output of `ssh-keyscan -t ed25519 <server-ip-or-hostname>`, run on your computer (add `-p 52222` once port 22 is closed) |
| `SSH_HOST` | the server's hostname or IP |
| `SSH_USER` | `deploy` |

Pushing the image needs no secret: the workflow uses the built-in `GITHUB_TOKEN`. In the repository's
Settings > Actions > General, "Workflow permissions" can stay on read-only; the build job asks for `packages: write`
itself.

## 11. First deploy

Push to `main` (or run the "Docker build and deploy" workflow by hand). The workflow tests, builds, pushes the image,
copies the files, then runs `deploy.sh`, which pulls, starts the stack, runs the migrations in a one-off container,
and checks `/up`.

To seed nothing and start with an empty shop, you are done. Create the first admin account from the server:

```bash
cd /opt/baskit
docker compose exec app php artisan tinker
# > \App\Models\User::create(['name' => 'Admin', 'email' => 'you@example.com', 'password' => '<strong password>', 'is_admin' => true, 'email_verified_at' => now()]);
```

To fill the shop with the demo catalogue (50 products, variants, coupons, reviews and two demo logins), run the seeders.
They need no Faker, are safe to run twice, and never overwrite existing products:

```bash
docker compose exec app php artisan db:seed --force
```

Product photos live in `database/seeders/images/`, named after the product slug (`wireless-headphones.jpg`), and are baked
into the image, so deploy after adding them. To wipe and reseed the demo every night, set `DEMO_MODE=true` in the server
`.env`. Never set it on a site with real data: `php artisan demo:reset` drops every table.

## 12. Let Google find the shop

Once the shop answers on `https://shop.younesghalloum.dev`:

1. Check the two files crawlers ask for first:

   ```bash
   curl -s https://shop.younesghalloum.dev/robots.txt
   curl -s https://shop.younesghalloum.dev/sitemap.xml | head -20
   ```

   `robots.txt` must list the private areas and end with `Sitemap: https://shop.younesghalloum.dev/sitemap.xml`.
   If it only says `Disallow: /`, `APP_ENV` in `/opt/baskit/.env` is not `production`. If the addresses inside the
   sitemap are wrong, fix `APP_URL`. Then run `docker compose up -d` so the containers read the `.env` again.
2. Open [Google Search Console](https://search.google.com/search-console) and add the domain
   `younesghalloum.dev`. Google asks for a `TXT` record: add it in Cloudflare (DNS only), then click Verify.
3. In Search Console, Sitemaps, submit `sitemap.xml`.
4. Use URL inspection on a product page to see what Google read. Indexing takes days to weeks.

## Everyday commands

```bash
cd /opt/baskit
./deploy.sh status                      # running tag and containers
docker compose logs -f app queue        # follow logs
./deploy.sh rollback                    # back to the previous image tag
./deploy.sh deploy <git-sha>            # deploy a specific version by hand
docker compose exec db pg_dump -U baskit baskit | gzip > "baskit-$(date +%F).sql.gz"   # backup
```

A rollback does not undo database migrations. Write migrations the previous release can still run against: add columns
and tables in one release, remove them in a later one.

## Troubleshooting

| Symptom | Check |
|---|---|
| `502 Bad Gateway` from the host Nginx | `docker compose ps`: is `web` healthy? `curl -i http://127.0.0.1:8081/up` |
| Links or assets use `http://` | `TRUSTED_PROXIES=*` and `APP_URL=https://...` in `.env`, then `docker compose up -d` |
| Login loops back to the login page | `SESSION_SECURE_COOKIE=true` needs HTTPS and `TRUSTED_PROXIES` set |
| `robots.txt` says `Disallow: /` | `APP_ENV` is not `production` in `/opt/baskit/.env` (step 12) |
| Addresses in `sitemap.xml` or the canonical tags are wrong | `APP_URL` must be `https://shop.younesghalloum.dev` |
| Emails not arriving | step 9: domain verified at Resend? `MAIL_PASSWORD` is the API key? sender on the verified domain? `docker compose logs queue` |
| `pull access denied` | step 4: log in to GHCR as `deploy`, or make the package public |
| Product images 404 | the `storage` volume must exist; `docker compose exec app ls storage/app/public` |
