# Paintings Hall

A painting gallery web app built as an end-to-end DevOps project. It runs three Docker containers, includes 18 automated tests, and uses a six-stage Jenkins pipeline.

**Stack:** Docker Compose · PHP 8.2 + Apache · PostgreSQL 15 · Grafana · Bash · Jenkins

**Resume line:** Built a Dockerized PHP/PostgreSQL/Grafana application with a six-stage Jenkins pipeline, 18 automated API and monitoring tests, and healthchecked containers.

## Project highlights

- Three containers: the app, PostgreSQL, and Grafana.
- PostgreSQL schema created automatically on first start.
- Grafana datasource and dashboard provisioned from code.
- 18 smoke tests covering the app, API validation, create/delete, and Grafana.
- Jenkins pipeline with six visible stages and archived logs.
- Isolated CI with its own ports, volume, project name, and temporary passwords.
- Automatic cleanup after every CI run, even when a step fails.

# Architecture diagram

Paintings Hall – Project workflow

              Developer
                  |
                  | Updates application code
                  v
             Git & GitHub
                  |
                  | Version control and source code repository
                  v
             Jenkins CI/CD
                  |
                  | Checkout → Build → Test → Archive
                  v
       Docker & Docker Compose
                  |
                  | Build and run application and database containers
                  |
          +-------+-------+
          |               |
          v               v
     PHP + Apache     PostgreSQL
          |               |
          | Gallery       | Artwork
          | application   | records
          |               |
          +-------+-------+
                  |
                  v
               Grafana
                  |
                  | Monitoring dashboards
                  | using the configured data source
                  v
          Visual Monitoring

## Run the app

You need **Docker** and **Git**. On Windows, use Git Bash. You do not need PHP, PostgreSQL, or Grafana installed.

1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop/) on Windows/macOS or [Docker Engine + Compose](https://docs.docker.com/engine/install/) on Linux, and [Git](https://git-scm.com/downloads).
2. Start Docker Desktop and wait until the engine is running.
3. Clone the repository:

```bash
git clone https://github.com/Yashwanthtr17/paintings-hall.git
cd paintings-hall
```

4. Create the environment file:

```bash
cp .env.example .env
```

5. Open `.env` and replace the `your_db_user`, `your_db_password`, `your_admin_user`, and `your_admin_password` placeholders with your own values.
6. Start all three services:

```bash
docker compose up -d --build
```

The first run downloads images and takes a few minutes.

7. Open the app:

| What | URL |
|---|---|
| Gallery | <http://localhost:8081> |
| JSON API | <http://localhost:8081/paintings.php> |
| Grafana | <http://localhost:3000> |

Log in to Grafana with the `GRAFANA_ADMIN_USER` and `GRAFANA_ADMIN_PASSWORD` from `.env`. To find them again, run:

```bash
grep DB .env
grep GRAFANA .env
```

To add a painting, fill in a title and artist, optionally add a year, image URL, and description, then click **Add painting**. Use the trash icon on a card to delete it.

## Check status

```bash
docker compose ps
```

All three services should show `Up (healthy)`.

| Check | Command | Expected |
|---|---|---|
| App page | `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8081/` | `200` |
| API | `curl -s http://localhost:8081/paintings.php` | `"success":true` |
| Grafana | `curl -s http://localhost:3000/api/health` | `"database":"ok"` |

If a service is unhealthy or stopped:

```bash
docker compose logs --tail=50 app
docker compose logs --tail=50 grafana
docker compose logs --tail=50 db
```

## Jenkins pipeline

Jenkins is separate from Docker Compose and runs the pipeline defined in `Jenkinsfile`.

It needs Docker, Git, Bash, curl, and the **Git** and **Pipeline** plugins. If Jenkins is not installed, use the [official guide](https://www.jenkins.io/doc/book/installing/).

Start it in WSL/Linux:

```bash
sudo systemctl start jenkins
```

Then open <http://localhost:8080>.

### Create the job once

1. Click **New Item** → name it `paintings-hall` → choose **Pipeline**.
2. Select **Pipeline script from SCM**.
3. Repository URL: `https://github.com/Yashwanthtr17/paintings-hall.git`
4. Branch: `*/master`
5. Script path: `Jenkinsfile`
6. Save and click **Build Now**.

### When the pipeline runs

- When you click **Build Now**.
- Automatically when `pollSCM` finds a new commit on `master`, checked about every 5 minutes.

Jenkins must be running for automatic builds. If it is stopped, open WSL and run `sudo systemctl start jenkins`.

### What each stage does

| # | Stage | What it does |
|---|---|---|
| 1 | Check Docker | Confirms Jenkins can use Docker and Docker Compose |
| 2 | Prepare Test Environment | Creates `.env.ci` with temporary passwords and validates Compose |
| 3 | Build App Image | Builds the PHP/Apache image from the Dockerfile |
| 4 | Check PHP Syntax | Runs `php -l` on every PHP file |
| 5 | Start Test Stack | Starts an isolated stack on ports `18081`, `13000`, and `15432`, then waits until it is healthy |
| 6 | Run 18 Smoke Tests | Tests the app, API, validation, create/delete, and Grafana |

The CI stack uses a separate project name, ports, volume, and credentials, so it does not touch your local app or data.

### After the stages

Jenkins always:

- Saves container logs to `compose-logs.txt`.
- Removes the CI stack, database volume, and `.env.ci`.
- Archives `compose-logs.txt` in the build.

It keeps the last 10 builds. To inspect a build, open **Build History** → build number → **Console Output**. In WSL, `sudo systemctl status jenkins` checks the service.

## Run the same checks locally

```bash
bash scripts/ci.sh
```

This one command performs the full flow: checks Docker, creates temporary passwords, validates Compose, builds, lints every PHP file, starts an isolated stack, waits for health, runs all 18 tests, saves logs, and cleans up.

A successful run ends with:

```text
Smoke test summary: 18 passed, 0 failed
CI passed.
```

On failure, cleanup still runs, and `compose-logs.txt` shows what happened.

## API

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/paintings.php` | List paintings |
| `POST` | `/paintings.php` | Add a painting |
| `DELETE` | `/paintings.php?id={id}` | Delete a painting |

```bash
curl -X POST http://localhost:8081/paintings.php \
  -H 'Content-Type: application/json' \
  -d '{"title":"The Starry Night","artist":"Vincent van Gogh","year":1889}'

curl -X DELETE http://localhost:8081/paintings.php?id=1
```

The API returns `400` for invalid input, `404` for a missing painting, and `405` for unsupported methods.

## Stop and clean up

```bash
docker compose down      # stop containers, keep the database
docker compose down -v   # stop containers, delete the database
```

## Troubleshooting

**`DB_PASSWORD must be set in .env`**
Run `cp .env.example .env` and edit the passwords.

**Docker commands are missing or the engine is not running**
Open Docker Desktop and wait until it is running.

**Port already in use**
Change `APP_PORT`, `GRAFANA_PORT`, or `DB_PORT` in `.env`, then run `docker compose up -d`.

**App cannot connect after changing the database password**
Reset the database with `docker compose down -v` and start again. This deletes local data.

**Jenkins cannot access Docker**
Inside WSL, run:

```bash
sudo usermod -aG docker jenkins
sudo systemctl restart jenkins
```

On Windows, also enable **Docker Desktop → Settings → Resources → WSL Integration** for Ubuntu.

**Jenkins says `Incorrect Java version` or `Failed to exec spawn helper`**
Jenkins was running while Java was updated. Restart it:

```bash
sudo systemctl restart jenkins
```

If the error continues, reinstall Java and restart Jenkins:

```bash
sudo apt update
sudo apt install --reinstall openjdk-21-jre openjdk-21-jre-headless
sudo systemctl restart jenkins
```
