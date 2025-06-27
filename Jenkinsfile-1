pipeline {
    agent any
    
    environment {
        // Configuración de VMs y repositorio
        VM_STAGING_IP = '192.168.72.128'
        STAGING_USER = 'clindata'
        STAGING_PATH = '/var/www/html/clindata'
        GITHUB_REPO = 'https://github.com/mcampos08/clindata-app-prueba.git'
        
        // Configuración de SonarQube
        SONARQUBE_SERVER = 'sq1'  // Nombre configurado en Jenkins
        SONAR_PROJECT_KEY = 'clindata-app-security'
        SONAR_PROJECT_NAME = 'Clindata App Security Analysis'
        
        // Umbrales de seguridad críticos
        MAX_HIGH_VULNS = '0'
        MAX_MEDIUM_VULNS = '5'
    }
    
    stages {
        stage('🔍 Checkout') {
            steps {
                echo '=== CLONANDO REPOSITORIO ==='
                git branch: 'main', url: "${GITHUB_REPO}"
                
                // Mostrar información del commit
                sh '''
                    echo "Commit actual:"
                    git log -1 --oneline
                    echo "Archivos en el proyecto:"
                    find . -name "*.php" | head -10
                '''
            }
        }
        
        stage('📊 SonarQube Analysis (SAST)') {
            steps {
                echo '=== ANÁLISIS ESTÁTICO DE CÓDIGO ==='
                script {
                    def scannerHome = tool 'SonarQubeScanner'
                    withSonarQubeEnv("${SONARQUBE_SERVER}") {
                        sh """
                            ${scannerHome}/bin/sonar-scanner \
                                -Dsonar.projectKey=${SONAR_PROJECT_KEY} \
                                -Dsonar.projectName='${SONAR_PROJECT_NAME}' \
                                -Dsonar.sources=. \
                                -Dsonar.language=php \
                                -Dsonar.sourceEncoding=UTF-8 \
                                -Dsonar.php.coverage.reportPaths=coverage.xml \
                                -Dsonar.exclusions=vendor/**,tests/**,*.log
                        """
                    }
                }
            }
        }
        
        stage('🛡️ SonarQube Quality Gate') {
            steps {
                echo '=== VALIDANDO QUALITY GATE ==='
                timeout(time: 5, unit: 'MINUTES') {
                    script {
                        def qg = waitForQualityGate()
                        if (qg.status != 'OK') {
                            echo "Quality Gate falló: ${qg.status}"
                            
                            // Obtener detalles de las vulnerabilidades críticas
                            sh """
                                echo "=== VULNERABILIDADES DETECTADAS ==="
                                curl -u admin:admin \
                                "http://localhost:9000/api/issues/search?componentKeys=${SONAR_PROJECT_KEY}&types=VULNERABILITY&severities=BLOCKER,CRITICAL" \
                                | jq '.issues[] | {rule: .rule, severity: .severity, message: .message}'
                            """
                            
                            error "Pipeline detenido: Vulnerabilidades críticas detectadas en SonarQube"
                        }
                    }
                }
            }
        }
        
        stage('🔍 OWASP Dependency Check (SCA)') {
            steps {
                echo '=== ANÁLISIS DE DEPENDENCIAS ==='
                sh '''
                    # Crear directorio para reportes si no existe
                    mkdir -p reports
                    
                    # Ejecutar OWASP Dependency Check
                    /opt/dependency-check/bin/dependency-check.sh \
                        --project "PHP-WebApp-Security" \
                        --scan . \
                        --format HTML \
                        --format JSON \
                        --format XML \
                        --out reports/ \
                        --failOnCVSS 7.0 \
                        --enableExperimental
                '''
                
                // Parsear y validar resultados críticos
                script {
                    if (fileExists('reports/dependency-check-report.json')) {
                        def dependencyReport = readJSON file: 'reports/dependency-check-report.json'
                        def highVulns = 0
                        def criticalVulns = 0
                        
                        dependencyReport.dependencies.each { dep ->
                            if (dep.vulnerabilities) {
                                dep.vulnerabilities.each { vuln ->
                                    if (vuln.severity == 'HIGH') highVulns++
                                    if (vuln.severity == 'CRITICAL') criticalVulns++
                                }
                            }
                        }
                        
                        echo "Vulnerabilidades encontradas - Críticas: ${criticalVulns}, Altas: ${highVulns}"
                        
                        if (criticalVulns > 0 || highVulns > Integer.parseInt(MAX_HIGH_VULNS)) {
                            error "Pipeline detenido: Vulnerabilidades críticas en dependencias (A06:2021)"
                        }
                    }
                }
            }
        }
        
        stage('📋 Generate SBOM with Syft') {
            steps {
                echo '=== GENERANDO SOFTWARE BILL OF MATERIALS ==='
                sh '''
                    # Generar SBOM en múltiples formatos
                    syft . -o spdx-json=reports/sbom.spdx.json
                    syft . -o cyclonedx-json=reports/sbom.cyclonedx.json
                    syft . -o table=reports/sbom.txt
                    
                    echo "=== RESUMEN DEL SBOM ==="
                    cat reports/sbom.txt | head -20
                '''
            }
        }
        
        stage('🔒 Security Validation') {
            steps {
                echo '=== VALIDACIÓN DE VULNERABILIDADES OWASP TOP 10 ==='
                script {
                    // Validar específicamente las vulnerabilidades mencionadas
                    def securityIssues = []
                    
                    // Verificar archivos PHP comunes con problemas de seguridad
                    sh '''
                        echo "=== BUSCANDO PATRONES DE VULNERABILIDADES ==="
                        
                        # A01: Broken Access Control
                        echo "Verificando Broken Access Control..."
                        grep -r "\\$_GET\\|\\$_POST\\|\\$_REQUEST" . --include="*.php" | grep -v "sanitize\\|filter" || true
                        
                        # A02: Cryptographic Failures  
                        echo "Verificando Cryptographic Failures..."
                        grep -r "md5\\|sha1\\|base64_encode" . --include="*.php" || true
                        
                        # A03: Injection
                        echo "Verificando Injection..."
                        grep -r "mysql_query\\|eval\\|system\\|exec" . --include="*.php" || true
                        
                        # A07: Authentication Failures
                        echo "Verificando Authentication Failures..."  
                        grep -r "session_start\\|\\$_SESSION" . --include="*.php" || true
                    '''
                }
            }
        }
        
        stage('📦 Build & Package') {
            steps {
                echo '=== PREPARANDO ARTEFACTOS ==='
                sh '''
                    # Crear directorio de build
                    mkdir -p build
                    
                    # Copiar archivos PHP (excluyendo archivos innecesarios)
                    rsync -av --exclude='.git' --exclude='reports' --exclude='build' . build/
                    
                    # Crear archivo de versión
                    echo "Build: ${BUILD_NUMBER}" > build/version.txt
                    echo "Commit: $(git rev-parse HEAD)" >> build/version.txt
                    echo "Date: $(date)" >> build/version.txt
                    
                    # Comprimir artefactos
                    tar -czf php-webapp-${BUILD_NUMBER}.tar.gz -C build .
                    
                    echo "Artefacto creado: php-webapp-${BUILD_NUMBER}.tar.gz"
                '''
            }
        }
        
        stage('🚀 Deploy to Staging') {
            steps {
                echo '=== DESPLEGANDO A VM-STAGING ==='
                sshagent(['staging-ssh-key']) {
                    sh '''
                        # Crear backup del despliegue anterior
                        ssh -o StrictHostKeyChecking=no ${STAGING_USER}@${VM_STAGING_IP} "
                            if [ -d ${STAGING_PATH} ]; then
                                sudo cp -r ${STAGING_PATH} ${STAGING_PATH}_backup_$(date +%Y%m%d_%H%M%S)
                            fi
                        "
                        
                        # Limpiar directorio de staging
                        ssh -o StrictHostKeyChecking=no ${STAGING_USER}@${VM_STAGING_IP} "
                            sudo rm -rf ${STAGING_PATH}/*
                        "
                        
                        # Copiar nuevos archivos
                        scp -o StrictHostKeyChecking=no php-webapp-${BUILD_NUMBER}.tar.gz ${STAGING_USER}@${VM_STAGING_IP}:/tmp/
                        
                        # Extraer archivos en staging
                        ssh -o StrictHostKeyChecking=no ${STAGING_USER}@${VM_STAGING_IP} "
                            cd /tmp
                            sudo tar -xzf php-webapp-${BUILD_NUMBER}.tar.gz -C ${STAGING_PATH}/
                            sudo chown -R www-data:www-data ${STAGING_PATH}
                            sudo chmod -R 755 ${STAGING_PATH}
                            rm php-webapp-${BUILD_NUMBER}.tar.gz
                        "
                        
                        # Verificar despliegue
                        ssh -o StrictHostKeyChecking=no ${STAGING_USER}@${VM_STAGING_IP} "
                            curl -f http://localhost/version.txt || echo 'Advertencia: No se pudo verificar el despliegue'
                        "
                    '''
                }
            }
        }
        
        stage('🧪 Basic Smoke Tests') {
            steps {
                echo '=== PRUEBAS BÁSICAS POST-DESPLIEGUE ==='
                script {
                    sleep(time: 10, unit: 'SECONDS') // Esperar que la app esté lista
                    
                    sh '''
                        # Verificar que la aplicación responde
                        echo "Verificando disponibilidad de la aplicación..."
                        curl -f http://${VM_STAGING_IP}/ || {
                            echo "ERROR: La aplicación no responde"
                            exit 1
                        }
                        
                        # Pruebas básicas de seguridad HTTP
                        echo "Verificando headers de seguridad básicos..."
                        curl -I http://${VM_STAGING_IP}/ | grep -i "server\\|x-powered-by" || true
                    '''
                }
            }
        }
    }
    
    post {
        always {
            echo '=== GENERANDO REPORTES FINALES ==='
            
            // Archivar reportes
            archiveArtifacts artifacts: 'reports/**/*', fingerprint: true, allowEmptyArchive: true
            
            // Publicar reportes HTML
            publishHTML([
                allowMissing: false,
                alwaysLinkToLastBuild: true,
                keepAll: true,
                reportDir: 'reports',
                reportFiles: 'dependency-check-report.html',
                reportName: 'OWASP Dependency Check Report'
            ])
            
            // Limpiar workspace
            cleanWs()
        }
        
        success {
            echo '✅ PIPELINE COMPLETADO EXITOSAMENTE'
            // Aquí puedes agregar notificaciones de éxito
        }
        
        failure {
            echo '❌ PIPELINE FALLÓ - REVISANDO LOGS'
            // Aquí puedes agregar notificaciones de fallo
        }
        
        unstable {
            echo '⚠️  PIPELINE INESTABLE - VERIFICAR WARNINGS'
        }
    }
}
