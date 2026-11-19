pipeline {
    agent any

    environment {
        IMAGE_NAME = 'paintings-hall'
        DOCKER_COMPOSE_FILE = 'docker-compose.yml'
        APP_PORT = '8081'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
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
