pipeline {
    agent any
    
    environment {
        // Configuración básica
        SONAR_PROJECT_KEY = 'clindata-app-security'
        SONAR_PROJECT_NAME = 'Clindata App Security Analysis'
        GITHUB_REPO = 'https://github.com/mcampos08/clindata-app-prueba.git'
    }
    
    stages {
        stage('🔍 Checkout') {
            steps {
                echo '=== CLONANDO REPOSITORIO ==='
                git branch: 'main', url: "${GITHUB_REPO}"
                
                sh '''
                    echo "Commit actual:"
                    git log -1 --oneline
                    echo "Archivos PHP encontrados:"
                    find . -name "*.php" | head -10
                '''
            }
        }
        
        stage('📊 SonarQube Analysis Test') {
            steps {
                echo '=== PROBANDO ANÁLISIS SONARQUBE ==='
                script {
                    // Verificar que SonarQube Scanner esté disponible
                    try {
                        def scannerHome = tool 'SonarQubeScanner'
                        echo "SonarQube Scanner encontrado en: ${scannerHome}"
                        
                        // Verificar conexión a SonarQube
                        sh '''
                            echo "Verificando conexión a SonarQube..."
                            curl -f http://localhost:9000/api/system/status || {
                                echo "ADVERTENCIA: No se puede conectar a SonarQube"
                                echo "Verificar que SonarQube esté corriendo en http://localhost:9000"
                            }
                        '''
                        
                        // Ejecutar análisis básico
                        withSonarQubeEnv('SonarQube-Local') {
                            sh """
                                ${scannerHome}/bin/sonar-scanner \
                                    -Dsonar.projectKey=${SONAR_PROJECT_KEY} \
                                    -Dsonar.projectName='${SONAR_PROJECT_NAME}' \
                                    -Dsonar.sources=. \
                                    -Dsonar.language=php \
                                    -Dsonar.sourceEncoding=UTF-8 \
                                    -Dsonar.exclusions=vendor/**,tests/**,*.log,Jenkinsfile
                            """
                        }
                    } catch (Exception e) {
                        echo "ERROR en SonarQube: ${e.getMessage()}"
                        error "Configuración de SonarQube incompleta"
                    }
                }
            }
        }
        
        stage('🔍 OWASP Dependency Check') {
            steps {
                echo '=== PROBANDO OWASP DEPENDENCY CHECK ==='
                script {
                    try {
                        // Crear directorio para reportes
                        sh 'mkdir -p reports'
                        
                        // Verificar que OWASP Dependency Check esté instalado
                        sh '''
                            if [ -f "/opt/dependency-check/bin/dependency-check.sh" ]; then
                                echo "OWASP Dependency Check encontrado"
                                /opt/dependency-check/bin/dependency-check.sh --version
                            else
                                echo "ADVERTENCIA: OWASP Dependency Check no encontrado en /opt/dependency-check/"
                                echo "Verificar instalación"
                            fi
                        '''
                        
                        // Ejecutar análisis básico (solo si está instalado)
                        sh '''
                            if [ -f "/opt/dependency-check/bin/dependency-check.sh" ]; then
                                /opt/dependency-check/bin/dependency-check.sh \
                                    --project "Clindata-Security-Test" \
                                    --scan . \
                                    --format HTML \
                                    --format JSON \
                                    --out reports/ \
                                    --failOnCVSS 10.0
                            else
                                echo "Creando reporte dummy para prueba..."
                                echo "<html><body><h1>OWASP Dependency Check - No instalado</h1></body></html>" > reports/dependency-check-report.html
                            fi
                        '''
                    } catch (Exception e) {
                        echo "ADVERTENCIA en OWASP Dependency Check: ${e.getMessage()}"
                        // No fallar el pipeline por esto en la prueba inicial
                    }
                }
            }
        }
        
        stage('📋 Syft SBOM Test') {
            steps {
                echo '=== PROBANDO SYFT PARA SBOM ==='
                script {
                    try {
                        sh '''
                            if command -v syft >/dev/null 2>&1; then
                                echo "Syft encontrado"
                                syft --version
                                syft . -o table=reports/sbom.txt
                                echo "SBOM generado exitosamente"
                            else
                                echo "ADVERTENCIA: Syft no está instalado"
                                echo "Para instalar: curl -sSfL https://raw.githubusercontent.com/anchore/syft/main/install.sh | sh -s -- -b /usr/local/bin"
                                echo "Creando SBOM dummy..."
                                echo "Syft no instalado - SBOM no disponible" > reports/sbom.txt
                            fi
                        '''
                    } catch (Exception e) {
                        echo "ADVERTENCIA en Syft: ${e.getMessage()}"
                    }
                }
            }
        }
        
        stage('📄 Verificar Reportes') {
            steps {
                echo '=== VERIFICANDO REPORTES GENERADOS ==='
                sh '''
                    echo "Contenido del directorio reports:"
                    ls -la reports/ || echo "Directorio reports no existe"
                    
                    echo "Archivos PHP en el proyecto:"
                    find . -name "*.php" -exec echo "Archivo: {}" \\;
                '''
            }
        }
    }
    
    post {
        always {
            echo '=== GUARDANDO REPORTES ==='
            
            // Crear directorio de reportes si no existe
            sh 'mkdir -p reports'
            
            // Archivar reportes
            archiveArtifacts artifacts: 'reports/**/*', fingerprint: true, allowEmptyArchive: true
            
            // Publicar reporte HTML si existe
            script {
                if (fileExists('reports/dependency-check-report.html')) {
                    publishHTML([
                        allowMissing: false,
                        alwaysLinkToLastBuild: true,
                        keepAll: true,
                        reportDir: 'reports',
                        reportFiles: 'dependency-check-report.html',
                        reportName: 'OWASP Dependency Check Report'
                    ])
                } else {
                    echo "Reporte HTML no encontrado, saltando publicación"
                }
            }
        }
        
        success {
            echo '✅ PIPELINE DE PRUEBA COMPLETADO'
        }
        
        failure {
            echo '❌ PIPELINE DE PRUEBA FALLÓ'
        }
    }
}
