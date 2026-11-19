# Paintings Hall

A simple painting gallery built with **PHP, PostgreSQL, Docker, Jenkins, Bash,
and Grafana**.

The project is small on purpose: three containers, one automation script, and
one Jenkins pipeline. It runs locally, needs no cloud account, and is easy to
explain in a DevOps interview.

## Stack

| Tool | Purpose |
|---|---|
| Docker + Docker Compose | Runs the application, database, and Grafana |
| PHP 8.2 + Apache | Serves the web page and JSON API |
| PostgreSQL 15 | Stores the paintings |
| Bash | Automates the CI pipeline |
| Jenkins | Runs the Bash pipeline on every build |
| Grafana | Shows a dashboard connected to PostgreSQL |

## Architecture

```text
Browser
   |
   v
PHP + Apache  --->  PostgreSQL
   |
   v
Grafana  --->  PostgreSQL

Jenkins  --->  bash scripts/ci.sh
                   |
                   +--> build
                   +--> check PHP
                   +--> start isolated stack
                   +--> run smoke tests
                   +--> save logs
                   +--> remove stack
```

The normal local stack and the CI stack are separate. CI uses different ports,
its own database volume, and temporary passwords, so it does not touch local
data.

## Project structure

```text
paintings-hall/
├── init-db/
│   └── 01-schema.sql
├── monitoring/
│   └── grafana/
│       ├── dashboards/paintings-hall.json
│       └── provisioning/
│           ├── dashboards/dashboard.yml
│           └── datasources/datasource.yml
├── scripts/
│   └── ci.sh
├── src/
│   ├── config.php
│   ├── index.php
│   ├── paintings.php
│   └── public/
│       ├── css/style.css
│       └── js/app.js
├── tests/
│   └── smoke.sh
├── Dockerfile
├── docker-compose.yml
├── Jenkinsfile
├── .env.example
└── README.md
```

## Run locally

### Requirements

- Docker Desktop or Docker Engine with Compose v2
- Git
- Bash (Linux, macOS, WSL, or Git Bash on Windows)
- Optional: Jenkins

### Start

```bash
cp .env.example .env
```

Edit `.env` and replace both passwords:

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

Then:

```bash
docker compose up -d --build
docker compose ps
```

Open:

- Application: **http://localhost:8081**
- API: **http://localhost:8081/paintings.php**
- Grafana: **http://localhost:3000**

Sign in to Grafana with the `GRAFANA_ADMIN_USER` and
`GRAFANA_ADMIN_PASSWORD` from `.env`.

## Automation

Everything is automated by one script:

```bash
bash scripts/ci.sh
```

It does six things:

1. Checks Docker, Docker Compose, curl, and the Docker daemon.
2. Creates a temporary `.env.ci` with random database and Grafana passwords.
3. Builds the image and checks every PHP file for syntax errors.
4. Starts an isolated stack on ports `18081`, `13000`, and `15432`.
5. Waits for the app and Grafana, then runs the smoke tests.
6. Saves logs to `compose-logs.txt` and removes the stack and database volume.

The script always cleans up, even if a step fails.

### What the smoke tests check

`tests/smoke.sh` runs 13 checks:

- The home page returns HTTP 200.
- The API returns a successful response.
- A painting can be added.
- The new painting appears in the API.
- Invalid input returns HTTP 400.
- Unsupported methods return HTTP 405.
- Grafana is healthy.
- The PostgreSQL datasource is healthy.
- The dashboard is provisioned.
- The dashboard contains the expected panel.
- Grafana can query the paintings table.

## Jenkins

The `Jenkinsfile` has only two stages:

1. **Check Docker** — confirms that Jenkins can use Docker.
2. **Build, Test, Cleanup** — runs `bash scripts/ci.sh`.

Jenkins does not contain the automation itself. It calls the same script that
runs on a laptop, so the pipeline can be tested before it reaches Jenkins.

### Jenkins setup

1. Install Jenkins with the **Pipeline** and **Git** plugins.
2. Make sure the Jenkins machine has Docker, Docker Compose v2, Bash, curl, and
   Git.
3. Give Jenkins access to Docker:
   - Linux: `sudo usermod -aG docker jenkins && sudo systemctl restart jenkins`
   - Windows: run Jenkins as the Docker Desktop user, or add the Jenkins service
     account to the `docker-users` group.
4. Create a **Pipeline** job.
5. Choose **Pipeline script from SCM**.
6. Set the repository URL and the script path to `Jenkinsfile`.
7. Run the build.

Jenkins checks out the repository automatically, runs the two stages, and
archives `compose-logs.txt`.

## Grafana

Grafana starts automatically with a provisioned PostgreSQL datasource and a
dashboard called **Paintings Hall Overview**. The dashboard shows:

- Total paintings
- Paintings added per day
- Paintings by artist
- Recent paintings

The datasource and dashboard are defined in `monitoring/grafana/`, so they are
created on every fresh start without manual clicking.

## API

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/paintings.php` | List all paintings |
| `POST` | `/paintings.php` | Add a painting |

Example:

```bash
curl -X POST http://localhost:8081/paintings.php \
  -H 'Content-Type: application/json' \
  -d '{"title":"The Starry Night","artist":"Vincent van Gogh","year":1889}'
```

## How to explain this project

Use these five points:

1. **Docker Compose** runs three services: the PHP app, PostgreSQL, and Grafana.
2. **PostgreSQL** stores the paintings, and the app talks to it with PDO.
3. **`scripts/ci.sh`** is the automation: it builds, checks, starts, tests, saves
   logs, and cleans up.
4. **Jenkins** runs that same script, so local and CI automation are identical.
5. **Grafana** reads from PostgreSQL and shows a dashboard that is created
   automatically from files in the repository.

A good sentence for an interview:

> I kept the pipeline in a Bash script so it is easy to run and debug locally.
> Jenkins is only the trigger. The script creates temporary credentials, uses
> isolated ports, runs 13 smoke tests, and removes everything when it finishes.

## Troubleshooting

### `DB_PASSWORD must be set in .env`

Create the environment file and replace the passwords:

```bash
cp .env.example .env
```

### The app cannot connect after changing the password

PostgreSQL only uses `POSTGRES_PASSWORD` when the database volume is created for
the first time. Reset the database (this deletes data):

```bash
docker compose down -v
docker compose up -d --build
```

Or change the password inside PostgreSQL:

```bash
docker compose exec db psql -U yashu -d paintings_hall-db \
  -c "ALTER USER yashu WITH PASSWORD 'new-password';"
docker compose up -d --force-recreate app grafana
```

### Port already in use

Change `APP_PORT`, `GRAFANA_PORT`, or `DB_PORT` in `.env`, then:

```bash
docker compose up -d
```

For the CI stack, set the ports before running the script:

```bash
APP_PORT=28081 GRAFANA_PORT=23000 DB_PORT=25432 bash scripts/ci.sh
```

### Jenkins cannot access Docker

Linux:

```bash
sudo usermod -aG docker jenkins
sudo systemctl restart jenkins
```

Windows: run Jenkins as the Docker Desktop user, or add the Jenkins service
account to the `docker-users` group, then restart Jenkins.

### WSL cannot reach Docker

Enable Docker Desktop integration for your WSL distribution, or run the Bash
script from Git Bash.

## Stop and clean up

Keep the database:

```bash
docker compose down
```

Delete the local database volume:

```bash
docker compose down -v
```

Remove the CI stack if a run was interrupted:

```bash
docker compose -p paintings-hall-ci --env-file .env.ci down -v
rm -f .env.ci
```
