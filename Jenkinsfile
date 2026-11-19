pipeline {
    agent any

    environment {
        IMAGE_NAME = 'paintings-hall'
        DOCKER_COMPOSE_FILE = 'docker-compose.yml'
        APP_PORT = '8081'
        DB_NAME = 'paintings_hall'
        DB_USER = 'paintings'
        DB_PASSWORD = 'paintings_password'
        GRAFANA_ADMIN_USER = 'admin'
        GRAFANA_ADMIN_PASSWORD = 'admin'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Fix Docker Permissions') {
            steps {
                sh '''
                    sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
                    newgrp docker 2>/dev/null || true
                    docker info > /dev/null 2>&1 && echo "Docker access OK" || echo "WARNING: Docker access limited"
                '''
            }
        }

        stage('Build Docker Image') {
            steps {
                sh 'docker compose build'
            }
        }

        stage('Run Application') {
            steps {
                sh 'docker compose up --build -d'
                sh 'sleep 15'
                sh '''
                    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${APP_PORT})
                    echo "Application HTTP status: ${HTTP_CODE}"
                    test "${HTTP_CODE}" = "200"
                '''
            }
        }

        stage('Verify Paintings API') {
            steps {
                sh '''
                    RESPONSE=$(curl -s http://localhost:${APP_PORT}/paintings.php)
                    echo "Paintings API response: ${RESPONSE}"
                    echo "${RESPONSE}" | grep -q '"success":true'
                '''
            }
        }

        stage('Cleanup') {
            steps {
                sh 'docker compose down'
            }
        }
    }

    post {
        always {
            archiveArtifacts artifacts: 'README.md, Dockerfile, docker-compose.yml, src/**', allowEmptyArchive: true
        }
        failure {
            sh 'docker compose down 2>/dev/null || true'
        }
    }
}
