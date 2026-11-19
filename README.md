# Paintings Hall

I built Paintings Hall as a local end-to-end DevOps project. It is a small
painting gallery where I can add paintings, view them in a clean web UI, query
them through a JSON API, and monitor the data in Grafana.

The whole stack runs with Docker Compose, and one Bash script automates the
build, test, and cleanup flow. Jenkins runs that same script, so the pipeline is
identical on my laptop and in CI.

I kept the project deliberately small: three containers, one automation script,
and one Jenkins pipeline. There are no cloud services, no paid tools, and no
manual setup steps.

## What the project does

- Shows a responsive gallery of paintings stored in PostgreSQL.
- Lets me add or delete a painting with a title, artist, year, image URL, and description.
- Exposes a JSON API at `/paintings.php` for reading and adding paintings.
- Provides a Grafana dashboard with live PostgreSQL data.
- Runs an 18-check smoke test suite against the app and Grafana.
- Builds, tests, and cleans up an isolated copy of the stack from one command.

## Stack

| Tool | What it does | Why I used it |
|---|---|---|
| Docker + Docker Compose | Runs the app, database, and Grafana | Reproducible local environment with one command |
| PHP 8.2 + Apache | Serves the web page and JSON API | Simple to containerize and easy to read |
| PostgreSQL 15 | Stores the paintings | Reliable relational database with a native Grafana datasource |
| Bash | Automates the CI pipeline | Portable, easy to run locally, and easy to debug |
| Jenkins | Triggers the pipeline | Pipeline as code and a real CI workflow |
| Grafana | Visualizes the data | Provisioned dashboards and datasource as code |

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
                   +--> build the image
                   +--> check PHP syntax
                   +--> start an isolated stack
                   +--> wait for health endpoints
                   +--> run 18 smoke tests
                   +--> save container logs
                   +--> remove the stack and database volume
```

The local stack and the CI stack are separate Compose projects. CI uses its own
ports, database volume, and temporary passwords, so it never touches local data.

## How I implemented it

### 1. Containerized the application

I started with a `Dockerfile` based on `php:8.2-apache`. I installed the
PostgreSQL PHP extension, enabled Apache rewrite rules, and copied `src/` into
the document root. The app uses PDO with prepared statements for every query.
The API supports listing, creating, and deleting paintings; delete requests
validate the id and return HTTP 404 when the painting does not exist.

### 2. Added PostgreSQL with a schema

I used `postgres:15-alpine` and mounted `init-db/01-schema.sql` into the
container's initialization directory. PostgreSQL runs that file only when the
data volume is created for the first time, which gives me a predictable schema
on a fresh machine.

### 3. Made startup reliable

The app waits for PostgreSQL with a healthcheck and `depends_on:
service_healthy`. Grafana waits for the database in the same way. All three
services have restart policies, so a temporary failure does not leave the stack
half-running.

### 4. Added Grafana as code

Instead of clicking through the Grafana UI, I provisioned everything from files:

- `monitoring/grafana/provisioning/datasources/datasource.yml` creates the
  PostgreSQL datasource with a fixed UID.
- `monitoring/grafana/provisioning/dashboards/dashboard.yml` tells Grafana where
  to load dashboards.
- `monitoring/grafana/dashboards/paintings-hall.json` defines four panels:
  total paintings, paintings added per day, paintings by artist, and recent
  paintings.

A fixed datasource UID was important. Without it, the dashboard breaks when a
fresh Grafana container gets a different generated UID.

### 5. Wrote one automation script

All automation lives in `scripts/ci.sh`. I did this on purpose: Jenkins should
be a trigger, not the place where the logic lives. The script:

1. Checks Docker, Docker Compose v2, curl, and daemon access.
2. Generates temporary database and Grafana passwords.
3. Writes an isolated `.env.ci` file.
4. Validates the Compose configuration.
5. Builds the image and checks every PHP file for syntax errors.
6. Starts the stack on ports `18081`, `13000`, and `15432`.
7. Waits for the app and Grafana health endpoints.
8. Runs the smoke tests.
9. Saves container logs to `compose-logs.txt`.
10. Removes the stack, database volume, and `.env.ci` file.

The cleanup runs from a Bash `trap`, so it happens even when a step fails.

### 6. Added Jenkins

The `Jenkinsfile` has two stages:

1. **Check Docker** — verifies Docker, Compose, and daemon access.
2. **Build, Test, Cleanup** — runs `bash scripts/ci.sh`.

Jenkins checks out the repository, runs the same script I run locally, and
archives `compose-logs.txt` after every build.

### 7. Wrote smoke tests

`tests/smoke.sh` runs 18 checks:

- The home page returns HTTP 200.
- `GET /paintings.php` reports success.
- `POST /paintings.php` creates a painting.
- The new painting appears in the gallery API.
- `POST` returns the new painting id.
- `DELETE` without an id returns HTTP 400.
- `DELETE` with an unknown id returns HTTP 404.
- `DELETE` removes the painting.
- The deleted painting no longer appears in the API.
- `POST` rejects a missing title or artist with HTTP 400.
- `POST` rejects a non-numeric year with HTTP 400.
- Unsupported methods return HTTP 405.
- Grafana is healthy.
- The PostgreSQL datasource is healthy.
- The dashboard is provisioned.
- The dashboard contains the Total Paintings panel.
- Grafana can query the paintings table.
- The Grafana query returns no API error.

### 8. Isolated CI from local development

| Setting | Local stack | CI stack |
|---|---|---|
| Compose project | `paintings-hall` | `paintings-hall-ci` |
| App port | `8081` | `18081` |
| Grafana port | `3000` | `13000` |
| Database port | `5432` | `15432` |
| Credentials | `.env` | Generated per run |
| Database volume | Persistent | Deleted after the run |

This means I can run `bash scripts/ci.sh` while my local stack is still up.

### 9. Handled secrets properly

The real `.env` is gitignored. `.env.example` contains placeholders only. CI
generates throwaway passwords for every run and removes them afterwards. I also
rotated an early credential and rewrote the Git history, so no real password
remains in any commit.

## Problems I ran into

| Problem | Cause | How I fixed it |
|---|---|---|
| Jenkins could not reach Docker | Jenkins runs in WSL, but the Docker CLI pointed at a Windows named pipe | Switched the Docker context to `unix:///var/run/docker.sock` |
| `permission denied` on `docker.sock` | The `jenkins` user was not in the `docker` group | Added it with `usermod -aG docker jenkins` and restarted Jenkins |
| SQL failed on the database name | The name `paintings_hall-db` contains a hyphen | Quoted the identifier as `"paintings_hall-db"` in SQL |
| Grafana dashboard lost its datasource | A fresh Grafana container generated a new datasource UID | Used a fixed UID, `paintings-postgres` |
| CI conflicted with local containers | Same project name and ports | Used a separate project, ports, volume, and ephemeral credentials |
| Scripts failed on Windows | CRLF line endings | Enforced LF with `.gitattributes` |
| A password was exposed early in the project | A real value was committed before `.env` was ignored | Rotated the password, rewrote history, and force-pushed the cleaned history |

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

It performs the full flow described above: prerequisites, temporary credentials,
build, PHP lint, isolated stack, health checks, 18 smoke tests, logs, and
cleanup.

The script always cleans up, even if a step fails.

## Jenkins

I run Jenkins locally and configured a Pipeline job from SCM:

- Repository: `https://github.com/Yashwanthtr17/paintings-hall.git`
- Branch: `*/master`
- Script path: `Jenkinsfile`

The pipeline has two stages:

1. **Check Docker** — confirms that Jenkins can use Docker.
2. **Build, Test, Cleanup** — runs `bash scripts/ci.sh`.

On my Windows machine, Jenkins runs inside WSL Ubuntu. I added the `jenkins`
user to the `docker` group and pointed the Docker CLI at the Unix socket so the
pipeline can use Docker Desktop.

### Jenkins setup on a new machine

1. Install Jenkins with the **Pipeline** and **Git** plugins.
2. Make sure the Jenkins machine has Docker, Docker Compose v2, Bash, curl, and
   Git.
3. Give Jenkins access to Docker:
   - Linux: `sudo usermod -aG docker jenkins && sudo systemctl restart jenkins`
   - Windows: run Jenkins inside WSL, or give the Jenkins service account access
     to Docker Desktop.
4. Create a **Pipeline** job.
5. Choose **Pipeline script from SCM**.
6. Set the repository URL, branch `*/master`, and script path `Jenkinsfile`.
7. Run the build.

The pipeline does not need a committed `.env`. It generates temporary
credentials, uses isolated ports, and removes its stack and database volume
after every run.

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
| `DELETE` | `/paintings.php?id={id}` | Delete a painting |

Example:

```bash
curl -X POST http://localhost:8081/paintings.php \
  -H 'Content-Type: application/json' \
  -d '{"title":"The Starry Night","artist":"Vincent van Gogh","year":1889}'
```

Delete a painting by id:

```bash
curl -X DELETE http://localhost:8081/paintings.php?id=1
```

## Security decisions

- The real `.env` is gitignored; `.env.example` contains placeholders only.
- CI generates throwaway database and Grafana passwords for every run.
- PostgreSQL is bound to `127.0.0.1`, not exposed to the network.
- Grafana admin credentials come from environment variables.
- The project contains no cloud credentials or deployment keys.
- Early credentials were rotated and removed from Git history.

## What I learned

- How to design a Compose stack with healthchecks, startup ordering, and restart
  policies.
- How to make CI repeatable with ephemeral credentials, separate ports, and a
  dedicated database volume.
- How Grafana provisioning works and why stable datasource UIDs matter.
- How to debug Docker access from WSL and fix Jenkins permissions.
- How to keep automation in one portable script instead of hiding it in a CI
  tool.
- How to handle secrets properly and clean them from Git history.

## How I explain this project

A 60-second explanation:

> I built a local end-to-end DevOps project with a PHP gallery, PostgreSQL,
> Grafana, Docker Compose, Bash, and Jenkins. The application and database run in
> containers, and Grafana is provisioned entirely from code. The key design
> choice is that all automation lives in one Bash script, so the exact same flow
> runs on my laptop and in Jenkins. The script generates temporary credentials,
> starts an isolated stack on different ports, runs 18 smoke tests, saves logs,
> and cleans everything up. Jenkins only triggers it and archives the logs.

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

For the CI stack, override the ports for one run:

```bash
APP_PORT=28081 GRAFANA_PORT=23000 DB_PORT=25432 bash scripts/ci.sh
```

### Jenkins cannot access Docker

Linux:

```bash
sudo usermod -aG docker jenkins
sudo systemctl restart jenkins
```

Windows with Jenkins in WSL:

```bash
sudo usermod -aG docker jenkins
sudo systemctl restart jenkins
docker context use default
```

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
