pipeline {
    agent any

    environment {
        SONARQUBE = 'sq1'  // Nombre configurado en Jenkins
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('SAST - SonarQube') {
            steps {
                withSonarQubeEnv("${SONARQUBE}") {
                    sh 'sonar-scanner'
                }
            }
        }

        stage('SBOM - Syft') {
            steps {
                sh 'syft . -o json > sbom.json'
            }
        }

        stage('SCA - OWASP Dependency-Check') {
            steps {
                sh '''
                mkdir -p dependency-check
                dependency-check --project "clindata-app" --scan . --format "HTML" --out dependency-check
                '''
            }
        }
    }

    post {
        always {
            archiveArtifacts artifacts: 'sbom.json', allowEmptyArchive: true
            archiveArtifacts artifacts: 'dependency-check/dependency-check-report.html', allowEmptyArchive: true
        }
    }
}
