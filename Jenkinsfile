pipeline {
    agent any

    options {
        timeout(time: 20, unit: 'MINUTES')
        disableConcurrentBuilds()
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

        stage('Build, Test, Cleanup') {
            steps {
                sh 'bash scripts/ci.sh'
            }
        }
    }

    post {
        always {
            archiveArtifacts artifacts: 'compose-logs.txt', allowEmptyArchive: true
        }
    }
}
