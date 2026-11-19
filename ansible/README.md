# Ansible — Configuration Management for Paintings Hall

This directory contains the Ansible playbook that automates deploying the Paintings Hall app using Docker Compose.

## Files

| File | Purpose |
|------|---------|
| `playbook.yml` | Main Ansible playbook — installs Docker, copies project files, runs `docker compose up`, and verifies the app. |
| `inventory.ini` | Inventory file — lists target hosts (`localhost` by default). |
| `vars.yml` | Variables — project directory, app port, Grafana port. |

## What the playbook does (stage by stage)

1. **Install required system packages** — installs `docker.io` and `python3-pip` on the target.
2. **Start and enable Docker service** — makes sure Docker is running and starts on boot.
3. **Install Docker SDK for Python** — installs the `docker` Python module so Ansible can talk to Docker.
4. **Create project directory** — creates `/opt/paintings-hall` on the target host.
5. **Copy project files** — copies `Dockerfile`, `docker-compose.yml`, `src/`, `init-db/`, and `.env.example` to the target.
6. **Create `.env`** — copies `.env.example` to `.env` on the target (review and update credentials before running).
7. **Build and start services** — runs `docker compose up --build -d` in the project directory.
8. **Wait for application to be ready** — polls `http://localhost:8081` until HTTP `200` is returned (up to 30 retries, 5s delay).
8. **Verify Paintings API** — polls `paintings.php` until the JSON response contains `"success"` (up to 10 retries, 5s delay).

## Key Ansible keywords used

- `hosts` — which target machines the playbook runs against.
- `become: yes` — runs tasks with elevated privileges (sudo).
- `vars_files` — loads external variable files (`vars.yml`).
- `tasks` — the list of actions to perform.
- `package` — installs system packages (apt/yum).
- `service` — manages system services (start/enable Docker).
- `pip` — installs Python packages.
- `file` — creates directories and sets permissions.
- `copy` — copies files from the control machine to the target.
- `command` / `args.chdir` — runs a shell command in a specific directory.
- `uri` — makes HTTP requests and checks responses.
- `register` — saves the result of a task to a variable.
- `until` / `retries` / `delay` — retries a task until a condition is met.

## How to run

```bash
# From the project root
ansible-playbook -i ansible/inventory.ini ansible/playbook.yml
```

Requirements: Ansible installed on the control machine, SSH access to the target (or `localhost` for local testing), and the target must allow package installation.
