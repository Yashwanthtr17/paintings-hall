# Paintings Hall

A simple painting gallery built with PHP and PostgreSQL, running in Docker.

This is a hands-on project where I'm bringing application development and DevOps practices together, from local development to cloud deployment.

## Tech Stack

- **Backend:** PHP 8.2
- **Database:** PostgreSQL 15
- **Frontend:** HTML, CSS, JavaScript
- **Web Server:** Apache
- **Containers:** Docker, Docker Compose
- **CI/CD:** Jenkins
- **Configuration Management:** Ansible
- **Monitoring:** Grafana
- **Version Control:** Git, GitHub
- **Cloud:** AWS EC2
- **Scripting:** Linux, Bash

## How it works

The application runs as a PHP + Apache container and connects to PostgreSQL through a Docker network. Grafana connects to PostgreSQL for database monitoring and visualization.

Jenkins handles the CI pipeline, while Ansible automates deployment tasks.

```text
                 GitHub
                    |
                    v
                 Jenkins
                    |
               Build & Test
                    |
                    v
             Docker Compose
                    |
          +---------+---------+
          |                   |
          v                   v
     PHP + Apache         PostgreSQL
          |                   |
          +---------+---------+
                    |
                  Grafana
                    |
                Dashboards

              Ansible
                  |
             Deployment
                  |
                AWS EC2
```

## Project Structure

```text
paintings-hall/
│
├── ansible/
│   ├── inventory.ini
│   ├── playbook.yml
│   └── vars.yml
│
├── init-db/
│   └── 01-schema.sql
│
├── monitoring/
│   └── grafana/
│       ├── dashboards/
│       └── provisioning/
│
├── src/
│   ├── index.php
│   ├── config.php
│   ├── paintings.php
│   └── public/
│       ├── css/
│       └── js/
│
├── tests/
│
├── Dockerfile
├── docker-compose.yml
├── Jenkinsfile
├── .gitignore
├── .env.example
└── README.md
```

## Run locally

**Requirements:** Docker, Docker Compose and Git.

Clone the repository:

```bash
git clone <repository-url>
cd paintings-hall
```

Configure environment variables:

```bash
cp .env.example .env
```

Start the application:

```bash
docker compose up --build -d
```

Check running containers:

```bash
docker compose ps
```

View application logs:

```bash
docker compose logs -f app
```

Open the application:

**http://localhost:8081**

### PostgreSQL access

Connect to the database:

```bash
docker compose exec db \
psql -U paintings -d paintings_hall
```

Useful commands:

```sql
\dt
SELECT * FROM paintings;
\q
```

### Container management

```bash
# Stop containers
docker compose down

# Restart containers
docker compose restart

# Rebuild application
docker compose up --build -d

# View all logs
docker compose logs -f

# Remove containers and database volume
docker compose down -v
```

Note: `docker compose down -v` deletes the persistent database volume and its data.

## CI/CD – Jenkins

A Jenkins Declarative Pipeline is used to automate the application build and validation process.

The `Jenkinsfile` contains five stages:

1. **Checkout** – Retrieves source code from Git.
2. **Build** – Builds the Docker image.
3. **Run Application** – Starts the application and database.
4. **API Verification** – Checks the paintings API response.
5. **Cleanup** – Stops the test environment and removes its named volume.

To run the pipeline:

1. Create a Pipeline job in Jenkins.
2. Select Pipeline script from SCM.
3. Configure the Git repository.
4. Set the script path to `Jenkinsfile`.
5. Run the build and inspect the stage results.

## Configuration Management – Ansible

Ansible is used to automate application deployment.

The playbook performs the following tasks:

- Installs Docker and required packages.
- Starts the Docker service.
- Creates the application directory.
- Copies project files to the target machine.
- Builds and starts containers.
- Checks application availability.
- Verifies the API response.

Run the playbook:

```bash
ansible-playbook