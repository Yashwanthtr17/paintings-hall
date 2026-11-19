pipeline {
    agent any

    options {
        timeout(time: 30, unit: 'MINUTES')
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '10', artifactNumToKeepStr: '5'))
        skipDefaultCheckout()
    }

    parameters {
        booleanParam(name: 'DEPLOY', defaultValue: false, description: 'Deploy to a remote Docker host after smoke tests pass')
        string(name: 'DEPLOY_HOST', defaultValue: '', description: 'Remote host (EC2 public IP or DNS)')
        string(name: 'DEPLOY_USER', defaultValue: 'ubuntu', description: 'SSH user for the remote host')
        string(name: 'DEPLOY_PATH', defaultValue: '/home/ubuntu/paintings-hall', description: 'Application directory on the remote host')
        string(name: 'SSH_CREDENTIALS_ID', defaultValue: 'ec2-ssh-key', description: 'Jenkins SSH credentials ID for the remote host')
    }

    environment {
        COMPOSE_PROJECT_NAME = 'paintings-hall-ci'
        APP_PORT = '18081'
        GRAFANA_PORT = '13000'
        DB_PORT = '15432'
        BASE_URL = 'http://localhost:18081'
        GRAFANA_URL = 'http://localhost:13000'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Check Prerequisites') {
            steps {
                sh '''
                    set -eu
                    docker --version
                    docker compose version
                    curl --version | head -n 1
                    docker info >/dev/null 2>&1 || {
                        echo "ERROR: the Jenkins user cannot access the Docker daemon."
                        echo "Add the Jenkins user to the docker group and restart the agent."
                        exit 1
                    }
                '''
            }
        }

        stage('Prepare CI Environment') {
            steps {
                sh '''
                    set -eu
                    umask 077

                    gen_password() {
                        if command -v openssl >/dev/null 2>&1; then
                            openssl rand -hex 24
                        else
                            od -An -N24 -tx1 /dev/urandom | tr -d ' \\n'
                        fi
                    }

                    {
                        echo "DB_NAME=paintings_hall"
                        echo "DB_USER=paintings"
                        echo "DB_PASSWORD=$(gen_password)"
                        echo "GRAFANA_ADMIN_USER=admin"
                        echo "GRAFANA_ADMIN_PASSWORD=$(gen_password)"
                        echo "APP_PORT=${APP_PORT}"
                        echo "GRAFANA_PORT=${GRAFANA_PORT}"
                        echo "DB_PORT=${DB_PORT}"
                    } > .env

                    chmod 600 .env
                    echo "Generated ephemeral CI credentials in .env"
                '''
            }
        }

        stage('Validate Configuration') {
            steps {
                sh 'docker compose -p "$COMPOSE_PROJECT_NAME" config --quiet'
            }
        }

        stage('Build Image') {
            steps {
                sh 'docker compose -p "$COMPOSE_PROJECT_NAME" build'
            }
        }

        stage('Lint PHP') {
            steps {
                sh 'sh ./scripts/lint-php.sh'
            }
        }

        stage('Start Stack') {
            steps {
                sh 'docker compose -p "$COMPOSE_PROJECT_NAME" up -d'
                sh 'sh ./scripts/wait-for-http.sh "$BASE_URL" 180'
                sh 'sh ./scripts/wait-for-http.sh "$GRAFANA_URL/api/health" 120'
            }
        }

        stage('Smoke Tests') {
            steps {
                sh 'sh ./tests/smoke.sh'
            }
        }

        stage('Deploy') {
            when {
                expression { params.DEPLOY }
            }
            steps {
                withEnv([
                    "DEPLOY_HOST=${params.DEPLOY_HOST}",
                    "DEPLOY_USER=${params.DEPLOY_USER}",
                    "DEPLOY_PATH=${params.DEPLOY_PATH}"
                ]) {
                    sshagent(credentials: [params.SSH_CREDENTIALS_ID]) {
                        sh 'sh ./scripts/deploy-ec2.sh'
                    }
                }
            }
        }
    }

    post {
        always {
            sh 'docker compose -p "$COMPOSE_PROJECT_NAME" ps -a || true'
            sh 'docker compose -p "$COMPOSE_PROJECT_NAME" logs --no-color > compose-logs.txt 2>&1 || true'
            archiveArtifacts artifacts: 'compose-logs.txt', allowEmptyArchive: true
            sh 'docker compose -p "$COMPOSE_PROJECT_NAME" down -v --remove-orphans || true'
            sh 'rm -f .env'
        }
        success {
            echo 'CI pipeline passed. The ephemeral CI stack was removed.'
        }
        failure {
            echo 'CI pipeline failed. Check compose-logs.txt and the stage output.'
        }
    }
}
