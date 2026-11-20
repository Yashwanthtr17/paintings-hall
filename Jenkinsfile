pipeline {
    agent any

    // Jenkins runs this pipeline when:
    // 1. Someone clicks "Build Now".
    // 2. pollSCM finds a new commit on master (checked about every 5 minutes).
    triggers {
        pollSCM('H/5 * * * *')
    }

    options {
        timeout(time: 25, unit: 'MINUTES')
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }

    stages {
        stage('Check Docker') {
            steps {
                sh '''
                    docker --version
                    docker compose version
                    docker info > /dev/null || {
                        echo "Jenkins cannot access Docker."
                        echo "Linux: sudo usermod -aG docker jenkins && sudo systemctl restart jenkins"
                        exit 1
                    }
                '''
            }
        }

        stage('Prepare Test Environment') {
            steps {
                sh '''
                    set -eu
                    DB_PASSWORD="$(openssl rand -hex 24)"
                    GRAFANA_ADMIN_PASSWORD="$(openssl rand -hex 24)"

                    cat > .env.ci <<EOF
DB_NAME=paintings_hall-db
DB_USER=yashu
DB_PASSWORD=${DB_PASSWORD}
GRAFANA_ADMIN_USER=yashu
GRAFANA_ADMIN_PASSWORD=${GRAFANA_ADMIN_PASSWORD}
APP_PORT=18081
GRAFANA_PORT=13000
DB_PORT=15432
EOF

                    chmod 600 .env.ci
                    docker compose -p paintings-hall-ci --env-file .env.ci config --quiet
                '''
            }
        }

        stage('Build App Image') {
            steps {
                sh 'docker compose -p paintings-hall-ci --env-file .env.ci build'
            }
        }

        stage('Check PHP Syntax') {
            steps {
                sh '''
                    docker compose -p paintings-hall-ci --env-file .env.ci run -T --rm --no-deps app \
                        sh -c 'find /var/www/html -name "*.php" -print0 | xargs -0 -n1 php -l'
                '''
            }
        }

        stage('Start Test Stack') {
            steps {
                sh '''
                    docker compose -p paintings-hall-ci --env-file .env.ci up -d

                    for i in $(seq 1 180); do
                        APP_CODE="$(curl -s -o /dev/null -w "%{http_code}" http://localhost:18081/ || true)"
                        GRAFANA_CODE="$(curl -s -o /dev/null -w "%{http_code}" http://localhost:13000/api/health || true)"

                        if [ "${APP_CODE}" = "200" ] && [ "${GRAFANA_CODE}" = "200" ]; then
                            echo "App and Grafana are ready."
                            break
                        fi

                        if [ "${i}" = "180" ]; then
                            echo "ERROR: the stack did not become ready in 180 seconds."
                            docker compose -p paintings-hall-ci --env-file .env.ci logs --no-color
                            exit 1
                        fi

                        sleep 1
                    done
                '''
            }
        }

        stage('Run 18 Smoke Tests') {
            steps {
                sh '''
                    ENV_FILE=.env.ci \
                    COMPOSE_ENV_FILE=.env.ci \
                    COMPOSE_PROJECT_NAME=paintings-hall-ci \
                    bash tests/smoke.sh
                '''
            }
        }
    }

    post {
        always {
            sh '''
                {
                    docker compose -p paintings-hall-ci --env-file .env.ci ps -a || true
                    docker compose -p paintings-hall-ci --env-file .env.ci logs --no-color || true
                } > compose-logs.txt 2>&1 || true

                docker compose -p paintings-hall-ci --env-file .env.ci down -v --remove-orphans >/dev/null 2>&1 || true
                rm -f .env.ci
            '''
            archiveArtifacts artifacts: 'compose-logs.txt', allowEmptyArchive: true
        }
        success {
            echo 'CI passed: image built, PHP syntax checked, stack started, and all 18 smoke tests passed.'
        }
        failure {
            echo 'CI failed. Open compose-logs.txt in the build artifacts to see what happened.'
        }
    }
}
