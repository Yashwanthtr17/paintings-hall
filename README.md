# Paintings Hall

A small painting gallery built with PHP and PostgreSQL, running in Docker with
Grafana monitoring and a Jenkins CI/CD pipeline.

## Tech stack

- **Backend:** PHP 8.2 (PDO + PostgreSQL)
- **Database:** PostgreSQL 15
- **Frontend:** HTML, CSS, JavaScript
- **Web server:** Apache
- **Containers:** Docker, Docker Compose
- **CI:** Jenkins (declarative pipeline)
- **Monitoring:** Grafana with a provisioned dashboard
- **Version control:** Git, GitHub

## Architecture

```text
                  GitHub
                     |
                     v
                  Jenkins
                     |
        +------------+------------+
        |                         |
        v                         v
  Build + lint              Smoke tests
        |                         |
        +------------+------------+
                     |
                     v
              Docker Compose
                     |
        +------------+------------+
        |            |            |
        v            v            v
   PHP + Apache   PostgreSQL    Grafana
        |            |            |
        +------------+------------+
                     |
              Provisioned dashboard
```

## Project structure

```text
paintings-hall/
├── init-db/
│   └── 01-schema.sql
├── monitoring/
│   └── grafana/
│       ├── dashboards/
│       │   └── paintings-hall.json
│       └── provisioning/
│           ├── dashboards/dashboard.yml
│           └── datasources/datasource.yml
├── scripts/
│   ├── deploy-ec2.sh
│   ├── lint-php.sh
│   └── wait-for-http.sh
├── src/
│   ├── index.php
│   ├── config.php
│   ├── paintings.php
│   └── public/
│       ├── css/style.css
│       └── js/app.js
├── tests/
│   └── smoke.sh
├── Dockerfile
├── docker-compose.yml
├── Jenkinsfile
├── Makefile
├── .env.example
└── .gitignore
```

## Quick start

**Requirements:** Docker, Docker Compose, and Git.

```bash
git clone https://github.com/Yashwanthtr17/paintings-hall.git
cd paintings-hall
cp .env.example .env
```

Edit `.env` and replace **both passwords** before starting:

```dotenv
DB_NAME=paintings_hall-db
DB_USER=yashu
DB_PASSWORD=change_this_password
GRAFANA_ADMIN_USER=yashu
GRAFANA_ADMIN_PASSWORD=change_this_password
APP_PORT=8081
GRAFANA_PORT=3000
DB_PORT=5432
```

Start the stack:

```bash
docker compose up -d --build
docker compose ps
```

Open:

- Application: **http://localhost:8081**
- Grafana: **http://localhost:3000**
- PostgreSQL: `localhost:5432` (bound to localhost only)

The first start creates the database schema from `init-db/01-schema.sql`.

## Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `DB_NAME` | `paintings_hall-db` | PostgreSQL database name |
| `DB_USER` | `yashu` | PostgreSQL user |
| `DB_PASSWORD` | required | PostgreSQL password |
| `GRAFANA_ADMIN_USER` | `yashu` | Grafana admin user |
| `GRAFANA_ADMIN_PASSWORD` | required | Grafana admin password |
| `APP_PORT` | `8081` | Host port for the app |
| `GRAFANA_PORT` | `3000` | Host port for Grafana |
| `DB_PORT` | `5432` | Host port for PostgreSQL (localhost only) |

The real `.env` is gitignored. `.env.example` contains placeholders only.

## Local commands

Using the Makefile (Linux, macOS, WSL, or Git Bash with `make`):

```bash
make help
make up       # build and start
make ps       # container status
make logs     # follow logs
make lint     # PHP syntax check
make smoke    # API + Grafana smoke tests
make test     # start, lint, and run smoke tests
make down     # stop, keep database volume
make reset    # stop and delete the database volume
```

Without `make`:

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f app
docker compose down
docker compose down -v   # WARNING: deletes the database volume
```

### Database access

```bash
docker compose exec db psql -U yashu -d paintings_hall-db
```

```sql
\dt
SELECT * FROM paintings;
\q
```

## API

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/paintings.php` | List all paintings |
| `POST` | `/paintings.php` | Add a painting (`title` and `artist` required) |

Example:

```bash
curl -X POST http://localhost:8081/paintings.php \
  -H 'Content-Type: application/json' \
  -d '{"title":"The Starry Night","artist":"Vincent van Gogh","year":1889}'
```

## Monitoring

Grafana starts automatically and provisions:

- A **PostgreSQL** datasource using the database credentials from `.env`
- A **Paintings Hall Overview** dashboard with:
  - Total paintings
  - Paintings added per day
  - Paintings by artist
  - Recent paintings

The dashboard is loaded from
`monitoring/grafana/dashboards/paintings-hall.json` on startup. Changes to the
JSON file are picked up automatically within 10 seconds.

Open **http://localhost:3000** and sign in with `GRAFANA_ADMIN_USER` /
`GRAFANA_ADMIN_PASSWORD` from `.env`.

## CI/CD with Jenkins

The `Jenkinsfile` is a declarative pipeline designed to run from a clean Git
checkout. It does **not** require a committed `.env` file.

### Pipeline stages

1. **Checkout** – checks out the repository.
2. **Check Prerequisites** – verifies Docker, Docker Compose, curl, and Docker
   daemon access.
3. **Prepare CI Environment** – generates ephemeral database and Grafana
   passwords and writes a temporary `.env`.
4. **Validate Configuration** – runs `docker compose config`.
5. **Build Image** – builds the PHP/Apache image.
6. **Lint PHP** – runs `php -l` on every PHP file.
7. **Start Stack** – starts the isolated CI stack and waits for the app and
   Grafana health endpoints.
8. **Smoke Tests** – verifies the home page, API GET/POST, validation errors,
   Grafana health, datasource health, dashboard provisioning, and a live
   database query.
9. **Deploy** – optional; only runs when the `DEPLOY` parameter is enabled.

The `post` block always archives container logs, tears down the CI stack with
`docker compose down -v`, and deletes the temporary `.env`.

### CI isolation

The pipeline uses a separate Compose project and alternate host ports so it can
run alongside a local or production stack:

| Setting | Value |
|---|---|
| Compose project | `paintings-hall-ci` |
| App port | `18081` |
| Grafana port | `13000` |
| Database port | `15432` |
| Credentials | Generated per build |
| Database volume | Deleted after every build |

### Jenkins job setup

1. Create a **Pipeline** job.
2. Select **Pipeline script from SCM**.
3. Set the repository URL.
4. Set the script path to `Jenkinsfile`.
5. Run the build.

The Jenkins agent must have:

- Linux shell (`sh`/`bash`)
- Docker Engine with Docker Compose v2
- `curl`
- `ssh` and `scp` (only for the optional deployment stage)
- The Jenkins user in the `docker` group:

```bash
sudo usermod -aG docker jenkins
sudo systemctl restart jenkins
```

No `sudo chmod 666 /var/run/docker.sock` is required.

### Optional deployment to EC2

The pipeline includes a **Deploy** stage that is disabled by default. It
requires the Jenkins **SSH Agent** plugin. To use it:

1. Install Docker and Docker Compose on the EC2 instance.
2. Create the application directory and a production `.env` on the server:

```bash
mkdir -p ~/paintings-hall
cd ~/paintings-hall
# Create .env with strong DB_PASSWORD and GRAFANA_ADMIN_PASSWORD values
```

3. Add an SSH private key credential in Jenkins (for example `ec2-ssh-key`).
4. Run the pipeline with:
   - `DEPLOY` = true
   - `DEPLOY_HOST` = EC2 public IP or DNS
   - `DEPLOY_USER` = `ubuntu` (or your SSH user)
   - `DEPLOY_PATH` = `/home/ubuntu/paintings-hall`
   - `SSH_CREDENTIALS_ID` = `ec2-ssh-key`

The deploy script copies the application files, verifies that the remote
`.env` exists, and runs `docker compose up -d --build` on the server. Secrets
are never copied from Jenkins.

You can also deploy manually:

```bash
DEPLOY_HOST=<ec2-host> DEPLOY_USER=ubuntu sh scripts/deploy-ec2.sh
```

## Security notes

- Never commit `.env` or real credentials. `.env.example` must contain
  placeholders only.
- Jenkins generates throwaway CI credentials for every build.
- Production deployment expects a pre-created `.env` on the server with strong
  passwords.
- PostgreSQL is bound to `127.0.0.1` on the host. Expose it through an SSH
  tunnel instead of opening port 5432 publicly.
- Restrict Grafana and the app in the EC2 security group to the ports you
  actually need.
- If a secret was ever committed, rotate it immediately and remove it from Git
  history (for example with `git filter-repo`).

## Troubleshooting

### `DB_PASSWORD must be set in .env`

Create the environment file:

```bash
cp .env.example .env
```

Then replace the placeholder passwords.

### The app cannot connect after changing `DB_PASSWORD`

PostgreSQL only applies `POSTGRES_PASSWORD` when the data volume is first
created. Either:

- Reset the local database (destroys data):

  ```bash
  docker compose down -v
  docker compose up -d --build
  ```

- Or change the password inside PostgreSQL:

  ```bash
  docker compose exec db psql -U yashu -d paintings_hall-db \
    -c "ALTER USER yashu WITH PASSWORD 'new-password';"
  ```

  Then update `.env` and restart the app:

  ```bash
  docker compose up -d --force-recreate app grafana
  ```

### Port already in use

Change `APP_PORT`, `GRAFANA_PORT`, or `DB_PORT` in `.env` and restart:

```bash
docker compose up -d
```

### Grafana dashboard is missing

Check provisioning logs:

```bash
docker compose logs grafana | grep -i provision
```

Confirm that the dashboard volume is mounted and that the provider path is
`/var/lib/grafana/dashboards` in
`monitoring/grafana/provisioning/dashboards/dashboard.yml`.

### Jenkins cannot access Docker

```bash
sudo usermod -aG docker jenkins
sudo systemctl restart jenkins
```

Then re-run the pipeline. The **Check Prerequisites** stage fails with a clear
message if the daemon is still unreachable.
