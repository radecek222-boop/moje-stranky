/**
 * Advanced Diagnostics - Ultra hloubková diagnostika
 *
 * Vylepšená diagnostika s detailním výstupem, exporty a AI analýzou
 */

class AdvancedDiagnostics {
    constructor() {
        this.isRunning = false;
        this.results = {};
        this.startTime = null;
    }

    /**
     * Spustit ultra hloubkovou diagnostiku
     */
    async runFullDiagnostics() {
        if (this.isRunning) {
            alert('Diagnostika již běží!');
            return;
        }

        this.isRunning = true;
        this.startTime = Date.now();
        this.results = {};

        const sections = [
            {
                name: 'SQL Advanced Analysis',
                action: 'analyze_sql_advanced',
                icon: '🗄️',
                description: 'Pokročilá analýza databáze'
            },
            {
                name: 'Code Quality',
                action: 'analyze_code_quality',
                icon: '📝',
                description: 'Kvalita kódu a dead code'
            },
            {
                name: 'Security Deep Scan',
                action: 'security_deep_scan',
                icon: '🔒',
                description: 'Hloubkový bezpečnostní scan'
            },
            {
                name: 'Performance Analysis',
                action: 'analyze_performance',
                icon: '⚡',
                description: 'Analýza výkonu'
            },
            {
                name: 'Dependencies',
                action: 'analyze_dependencies',
                icon: '🔗',
                description: 'Mapování závislostí'
            },
            {
                name: 'File Structure',
                action: 'analyze_file_structure',
                icon: '📁',
                description: 'Struktura projektu'
            },
            {
                name: 'API Deep Test',
                action: 'test_api_endpoints_deep',
                icon: '🌐',
                description: 'Detailní test API'
            }
        ];

        this.displayHeader();

        for (const section of sections) {
            await this.runSection(section);
        }

        this.displaySummary();
        this.isRunning = false;
    }

    /**
     * Zobrazit hlavičku diagnostiky
     */
    displayHeader() {
        const output = document.getElementById('console-output');
        output.innerHTML = '';

        const header = `
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 WGS SERVICE - ULTRA HLOUBKOVÁ DIAGNOSTIKA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Začátek analýzy: ${new Date().toLocaleString('cs-CZ')}
Režim: Produkčně bezpečný (READ-ONLY)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
`;
        this.log(header, 'header');
        this.log('');
    }

    /**
     * Spustit jednu sekci diagnostiky
     */
    async runSection(section) {
        this.log('');
        this.log(`${section.icon} ${section.name.toUpperCase()}`, 'section-header');
        this.log(`─`.repeat(79), 'separator');
        this.log(`📊 ${section.description}...`, 'info');
        this.log('');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const response = await fetch(`/api/advanced_diagnostics_api.php?action=${section.action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ csrf_token: csrfToken })
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.results[section.action] = data.data;
                this.displayResults(section, data.data);
            } else {
                this.log(`❌ Chyba: ${data.message}`, 'error');
            }
        } catch (error) {
            this.log(`❌ Chyba při analýze: ${error.message}`, 'error');
        }
    }

    /**
     * Zobrazit výsledky podle typu sekce
     */
    displayResults(section, data) {
        switch (section.action) {
            case 'analyze_sql_advanced':
                this.displaySQLResults(data);
                break;
            case 'analyze_code_quality':
                this.displayCodeQualityResults(data);
                break;
            case 'security_deep_scan':
                this.displaySecurityResults(data);
                break;
            case 'analyze_performance':
                this.displayPerformanceResults(data);
                break;
            case 'analyze_dependencies':
                this.displayDependencyResults(data);
                break;
            case 'analyze_file_structure':
                this.displayFileStructureResults(data);
                break;
            case 'test_api_endpoints_deep':
                this.displayAPIResults(data);
                break;
        }
    }

    /**
     * Zobrazit SQL výsledky
     */
    displaySQLResults(data) {
        // Missing Indexes
        if (data.missing_indexes && data.missing_indexes.length > 0) {
            this.log(`🔍 CHYBĚJÍCÍ INDEXY (${data.missing_indexes.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            const critical = data.missing_indexes.filter(idx => idx.priority === 'high');
            const medium = data.missing_indexes.filter(idx => idx.priority === 'medium');

            this.log(`🔴 Kritické: ${critical.length}`, 'error');
            this.log(`🟡 Střední: ${medium.length}`, 'warning');
            this.log('');

            // Zobrazit prvních 10 kritických
            critical.slice(0, 10).forEach((idx, i) => {
                this.log(`${i + 1}. ${idx.table}.${idx.column}`, 'warning');
                this.log(`   📋 Důvod: ${idx.reason}`, 'info');
                this.log(`   ⚡ Dopad: ${idx.estimated_impact}`, 'info');
                this.log(`   💻 SQL: ${idx.sql_command}`, 'code');
                this.log('');
            });

            if (data.missing_indexes.length > 10) {
                this.log(`... a dalších ${data.missing_indexes.length - 10} indexů`, 'info');
            }
        } else {
            this.log('✅ Všechny důležité indexy jsou přítomny', 'success');
        }

        this.log('');

        // Orphaned Records
        if (data.orphaned_records && data.orphaned_records.length > 0) {
            this.log(`👻 ORPHANED RECORDS (${data.orphaned_records.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            data.orphaned_records.forEach(orphan => {
                this.log(`📦 ${orphan.table}: ${orphan.count} záznamů`, 'warning');
                this.log(`   ${orphan.description}`, 'info');
                this.log(`   🔧 Doporučení: ${orphan.recommended_action}`, 'info');
                this.log('');
            });
        } else {
            this.log('✅ Žádné orphaned records', 'success');
        }

        this.log('');

        // Data Integrity
        if (data.data_integrity_issues && data.data_integrity_issues.length > 0) {
            this.log(`⚠️ PROBLÉMY S INTEGRITOU DAT (${data.data_integrity_issues.length})`, 'error');
            this.log('═'.repeat(79), 'separator');

            data.data_integrity_issues.forEach(issue => {
                this.log(`📊 ${issue.table}.${issue.column}`, 'error');
                this.log(`   ${issue.issue}`, 'warning');
                this.log('');
            });
        } else {
            this.log('✅ Data integrity OK', 'success');
        }

        // Table Statistics
        if (data.table_statistics && data.table_statistics.length > 0) {
            this.log('');
            this.log('📊 TOP 5 NEJVĚTŠÍCH TABULEK', 'info');
            this.log('─'.repeat(79), 'separator');

            data.table_statistics.slice(0, 5).forEach((table, i) => {
                const sizeMB = (parseInt(table.DATA_LENGTH) / 1048576).toFixed(2);
                this.log(`${i + 1}. ${table.TABLE_NAME}: ${sizeMB} MB (${table.TABLE_ROWS} řádků)`, 'info');
            });
        }

        this.log('');
    }

    /**
     * Zobrazit výsledky kvality kódu
     */
    displayCodeQualityResults(data) {
        // Dead Code
        if (data.dead_code && data.dead_code.length > 0) {
            this.log(`💀 DEAD CODE (${data.dead_code.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            data.dead_code.slice(0, 10).forEach((item, i) => {
                this.log(`${i + 1}. ${item.type}: ${item.name}`, 'warning');
                item.locations.forEach(loc => {
                    this.log(`   📄 ${loc.file}:${loc.line}`, 'info');
                });
                this.log(`   💡 ${item.recommendation}`, 'info');
                this.log('');
            });

            if (data.dead_code.length > 10) {
                this.log(`... a dalších ${data.dead_code.length - 10} položek`, 'info');
            }
        } else {
            this.log('✅ Žádný dead code detekován', 'success');
        }

        this.log('');

        // Duplicates
        if (data.duplicates && data.duplicates.length > 0) {
            this.log(`📋 DUPLICITNÍ SOUBORY (${data.duplicates.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            data.duplicates.forEach((dup, i) => {
                this.log(`${i + 1}. ${dup.type}`, 'warning');
                this.log(`   📄 ${dup.file1}`, 'info');
                this.log(`   📄 ${dup.file2}`, 'info');
                this.log(`   💡 ${dup.recommendation}`, 'info');
                this.log('');
            });
        } else {
            this.log('✅ Žádné duplicitní soubory', 'success');
        }

        this.log('');

        // Complexity
        if (data.complexity && data.complexity.length > 0) {
            this.log(`🔢 VYSOKÁ KOMPLEXITA (${data.complexity.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            const critical = data.complexity.filter(c => c.severity === 'high');
            this.log(`🔴 Kritické (>20): ${critical.length}`, 'error');
            this.log(`🟡 Střední (10-20): ${data.complexity.length - critical.length}`, 'warning');
            this.log('');

            critical.slice(0, 5).forEach((item, i) => {
                this.log(`${i + 1}. ${item.function}() - Komplexita: ${item.complexity}`, 'error');
                this.log(`   📄 ${item.file}`, 'info');
                this.log(`   💡 ${item.recommendation}`, 'info');
                this.log('');
            });
        } else {
            this.log('✅ Komplexita kódu v normě', 'success');
        }

        this.log('');

        // Syntax Issues
        if (data.syntax_issues && data.syntax_issues.length > 0) {
            this.log(`❌ SYNTAX ERRORS (${data.syntax_issues.length})`, 'error');
            this.log('═'.repeat(79), 'separator');

            data.syntax_issues.forEach((issue, i) => {
                this.log(`${i + 1}. ${issue.file}`, 'error');
                this.log(`   ${issue.error}`, 'error');
                this.log('');
            });
        } else {
            this.log('✅ Žádné syntax errors', 'success');
        }

        this.log('');

        // Best Practices
        if (data.best_practices && data.best_practices.length > 0) {
            this.log(`⚠️ BEST PRACTICES VIOLATIONS (${data.best_practices.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            const critical = data.best_practices.filter(bp => bp.severity === 'critical');
            const high = data.best_practices.filter(bp => bp.severity === 'high');

            this.log(`🔴 Kritické: ${critical.length}`, 'error');
            this.log(`🟠 Vysoké: ${high.length}`, 'warning');
            this.log('');

            [...critical, ...high].slice(0, 10).forEach((bp, i) => {
                this.log(`${i + 1}. [${bp.severity.toUpperCase()}] ${bp.file}`, bp.severity === 'critical' ? 'error' : 'warning');
                this.log(`   ⚠️ ${bp.issue}`, 'warning');
                this.log(`   💡 ${bp.recommendation}`, 'info');
                this.log('');
            });
        } else {
            this.log('✅ Best practices dodrženy', 'success');
        }
    }

    /**
     * Zobrazit bezpečnostní výsledky
     */
    displaySecurityResults(data) {
        const totalIssues =
            (data.xss_risks?.length || 0) +
            (data.sql_injection_risks?.length || 0) +
            (data.csrf_missing?.length || 0) +
            (data.file_upload_risks?.length || 0);

        if (totalIssues === 0) {
            this.log('✅ Žádná kritická bezpečnostní rizika nenalezena', 'success');
            this.log('');
            return;
        }

        this.log(`🔒 CELKEM NALEZENO: ${totalIssues} bezpečnostních rizik`, 'warning');
        this.log('═'.repeat(79), 'separator');
        this.log('');

        // XSS Risks
        if (data.xss_risks && data.xss_risks.length > 0) {
            this.log(`🔴 XSS RIZIKA (${data.xss_risks.length})`, 'error');
            this.log('─'.repeat(79), 'separator');

            data.xss_risks.slice(0, 5).forEach((risk, i) => {
                this.log(`${i + 1}. ${risk.file}:${risk.line}`, 'error');
                this.log(`   ⚠️ ${risk.pattern}`, 'warning');
                this.log(`   Severity: ${risk.severity.toUpperCase()}`, 'error');

                if (risk.context && risk.context.length > 0) {
                    this.log('   📝 Kontext:', 'info');
                    risk.context.forEach(ctx => {
                        const prefix = ctx.is_error_line ? '   >>> ' : '       ';
                        this.log(`${prefix}${ctx.line}: ${ctx.content}`, ctx.is_error_line ? 'error' : 'code');
                    });
                }
                this.log('');
            });

            if (data.xss_risks.length > 5) {
                this.log(`... a dalších ${data.xss_risks.length - 5} XSS rizik`, 'warning');
            }
        }

        this.log('');

        // SQL Injection Risks
        if (data.sql_injection_risks && data.sql_injection_risks.length > 0) {
            this.log(`🔴 SQL INJECTION RIZIKA (${data.sql_injection_risks.length})`, 'error');
            this.log('─'.repeat(79), 'separator');

            data.sql_injection_risks.slice(0, 5).forEach((risk, i) => {
                this.log(`${i + 1}. ${risk.file}:${risk.line}`, 'error');
                this.log(`   ⚠️ ${risk.pattern}`, 'warning');
                this.log(`   Severity: CRITICAL`, 'error');

                if (risk.context && risk.context.length > 0) {
                    this.log('   📝 Kontext:', 'info');
                    risk.context.forEach(ctx => {
                        const prefix = ctx.is_error_line ? '   >>> ' : '       ';
                        this.log(`${prefix}${ctx.line}: ${ctx.content}`, ctx.is_error_line ? 'error' : 'code');
                    });
                }
                this.log('');
            });
        }

        this.log('');

        // CSRF Missing
        if (data.csrf_missing && data.csrf_missing.length > 0) {
            this.log(`🟠 CHYBĚJÍCÍ CSRF OCHRANA (${data.csrf_missing.length})`, 'warning');
            this.log('─'.repeat(79), 'separator');

            data.csrf_missing.forEach((csrf, i) => {
                this.log(`${i + 1}. ${csrf.file}`, 'warning');
                this.log(`   ${csrf.issue}`, 'info');
                this.log('');
            });
        }

        // Exposed Files
        if (data.exposed_files && data.exposed_files.length > 0) {
            this.log(`⚠️ EXPONOVANÉ SOUBORY (${data.exposed_files.length})`, 'warning');
            this.log('─'.repeat(79), 'separator');

            data.exposed_files.forEach((file, i) => {
                this.log(`${i + 1}. ${file.file} [${file.severity.toUpperCase()}]`, file.severity === 'critical' ? 'error' : 'warning');
                this.log(`   💡 ${file.recommendation}`, 'info');
                this.log('');
            });
        }
    }

    /**
     * Zobrazit výsledky výkonu
     */
    displayPerformanceResults(data) {
        // Large Files
        if (data.large_files && data.large_files.length > 0) {
            this.log(`📦 VELKÉ SOUBORY (${data.large_files.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            data.large_files.slice(0, 10).forEach((file, i) => {
                const severity = file.size_bytes > 2000000 ? 'error' : 'warning';
                this.log(`${i + 1}. ${file.file}: ${file.size}`, severity);
                this.log(`   💡 ${file.recommendation}`, 'info');
                this.log('');
            });

            if (data.large_files.length > 10) {
                this.log(`... a dalších ${data.large_files.length - 10} souborů`, 'info');
            }
        } else {
            this.log('✅ Žádné nadměrně velké soubory', 'success');
        }

        this.log('');

        // Unminified Assets
        if (data.unminified_assets && data.unminified_assets.length > 0) {
            this.log(`⚡ NEMINIFIKOVANÉ ASSETS (${data.unminified_assets.length})`, 'info');
            this.log('═'.repeat(79), 'separator');

            data.unminified_assets.slice(0, 10).forEach((asset, i) => {
                this.log(`${i + 1}. ${asset.file}: ${asset.size}`, 'info');
                this.log(`   💾 Potenciální úspora: ${asset.potential_savings}`, 'success');
                this.log('');
            });

            if (data.unminified_assets.length > 10) {
                this.log(`... a dalších ${data.unminified_assets.length - 10} souborů`, 'info');
            }
        } else {
            this.log('✅ Všechny assets minifikovány', 'success');
        }

        this.log('');

        // Missing Lazy Load
        if (data.missing_lazy_load && data.missing_lazy_load.length > 0) {
            this.log(`🖼️ CHYBĚJÍCÍ LAZY LOAD (${data.missing_lazy_load.length})`, 'info');
            this.log('═'.repeat(79), 'separator');

            const uniqueFiles = [...new Set(data.missing_lazy_load.map(i => i.file))];
            this.log(`Obrázky bez lazy loading v ${uniqueFiles.length} souborech`, 'warning');
            this.log('');

            uniqueFiles.slice(0, 5).forEach((file, i) => {
                const count = data.missing_lazy_load.filter(i => i.file === file).length;
                this.log(`${i + 1}. ${file}: ${count} obrázků`, 'warning');
            });
        } else {
            this.log('✅ Lazy loading správně nastaven', 'success');
        }
    }

    /**
     * Zobrazit výsledky závislostí
     */
    displayDependencyResults(data) {
        // Missing Files
        if (data.missing_files && data.missing_files.length > 0) {
            this.log(`❌ CHYBĚJÍCÍ SOUBORY (${data.missing_files.length})`, 'error');
            this.log('═'.repeat(79), 'separator');

            data.missing_files.forEach((missing, i) => {
                this.log(`${i + 1}. ${missing.file}`, 'error');
                this.log(`   ❌ Chybí: ${missing.missing_dependency}`, 'error');
                this.log('');
            });
        } else {
            this.log('✅ Všechny závislosti nalezeny', 'success');
        }

        this.log('');

        // Circular Dependencies
        if (data.circular_dependencies && data.circular_dependencies.length > 0) {
            this.log(`🔄 CYKLICKÉ ZÁVISLOSTI (${data.circular_dependencies.length})`, 'warning');
            this.log('═'.repeat(79), 'separator');

            data.circular_dependencies.forEach((circ, i) => {
                this.log(`${i + 1}. Cyklus:`, 'warning');
                this.log(`   ${circ.file1}`, 'info');
                this.log(`   ↔️`, 'info');
                this.log(`   ${circ.file2}`, 'info');
                this.log(`   💡 ${circ.recommendation}`, 'info');
                this.log('');
            });
        } else {
            this.log('✅ Žádné cyklické závislosti', 'success');
        }

        this.log('');

        // Dependency Map Summary
        if (data.require_map) {
            const totalFiles = Object.keys(data.require_map).length;
            const totalDeps = Object.values(data.require_map).reduce((sum, deps) => sum + deps.length, 0);

            this.log('📊 DEPENDENCY MAP STATISTICS', 'info');
            this.log('─'.repeat(79), 'separator');
            this.log(`Total Files: ${totalFiles}`, 'info');
            this.log(`Total Dependencies: ${totalDeps}`, 'info');
            this.log(`Average Deps per File: ${(totalDeps / totalFiles).toFixed(2)}`, 'info');
        }
    }

    /**
     * Zobrazit výsledky struktury souborů
     */
    displayFileStructureResults(data) {
        this.log('📁 STRUKTURA PROJEKTU', 'info');
        this.log('═'.repeat(79), 'separator');
        this.log(`Celkem souborů: ${data.total_files}`, 'info');
        this.log('');

        // By Extension
        if (data.by_extension) {
            this.log('📄 PODLE TYPU SOUBORU:', 'info');
            this.log('─'.repeat(79), 'separator');

            const sorted = Object.entries(data.by_extension).sort((a, b) => b[1] - a[1]);
            sorted.slice(0, 10).forEach(([ext, count]) => {
                this.log(`  .${ext || '(no ext)'}: ${count} souborů`, 'info');
            });
        }

        this.log('');

        // Large Directories
        if (data.large_directories) {
            this.log('📦 NEJVĚTŠÍ ADRESÁŘE:', 'info');
            this.log('─'.repeat(79), 'separator');

            Object.entries(data.large_directories).slice(0, 10).forEach(([dir, count], i) => {
                this.log(`  ${i + 1}. ${dir}: ${count} souborů`, 'info');
            });
        }

        this.log('');

        // Deep Nesting
        if (data.deep_nesting && data.deep_nesting.length > 0) {
            this.log(`⚠️ PŘÍLIŠ HLUBOKÉ VNOŘOVÁNÍ (${data.deep_nesting.length})`, 'warning');
            this.log('─'.repeat(79), 'separator');

            data.deep_nesting.slice(0, 5).forEach((file, i) => {
                this.log(`  ${i + 1}. ${file}`, 'warning');
            });
        }
    }

    /**
     * Zobrazit výsledky API testů
     */
    displayAPIResults(data) {
        if (!data || data.length === 0) {
            this.log('⚠️ Žádná API data', 'warning');
            return;
        }

        this.log('🌐 API ENDPOINTY DEEP TEST', 'info');
        this.log('═'.repeat(79), 'separator');
        this.log('');

        data.forEach((endpoint, i) => {
            const statusIcon = endpoint.status === 'OK' ? '✅' : '❌';
            const statusClass = endpoint.status === 'OK' ? 'success' : 'error';

            this.log(`${i + 1}. ${statusIcon} ${endpoint.endpoint}`, statusClass);
            this.log(`   HTTP Code: ${endpoint.http_code}`, 'info');
            this.log(`   Response Time: ${endpoint.response_time}`, 'info');
            this.log(`   JSON Valid: ${endpoint.is_json ? 'Yes' : 'No'}`, endpoint.is_json ? 'success' : 'warning');
            this.log('');
        });
    }

    /**
     * Zobrazit finální shrnutí
     */
    displaySummary() {
        const elapsed = ((Date.now() - this.startTime) / 1000).toFixed(2);

        this.log('');
        this.log('━'.repeat(79), 'separator');
        this.log('📊 FINÁLNÍ SHRNUTÍ', 'header');
        this.log('━'.repeat(79), 'separator');
        this.log('');

        // Spočítat celkový počet problémů
        const counts = this.countIssues();

        this.log(`🔴 Kritické problémy: ${counts.critical}`, counts.critical > 0 ? 'error' : 'success');
        this.log(`🟠 Vysoká priorita: ${counts.high}`, counts.high > 0 ? 'warning' : 'success');
        this.log(`🟡 Střední priorita: ${counts.medium}`, 'info');
        this.log(`ℹ️ Informační: ${counts.info}`, 'info');
        this.log('');
        this.log(`⏱️ Čas diagnostiky: ${elapsed}s`, 'info');
        this.log('');

        // Export možnosti
        this.log('💾 EXPORT MOŽNOSTI', 'info');
        this.log('─'.repeat(79), 'separator');
        this.log('Diagnostika dokončena. Data jsou k dispozici pro export:', 'info');
        this.log('  • JSON Export - pro další zpracování', 'info');
        this.log('  • Text Report - pro dokumentaci', 'info');
        this.log('  • AI Analysis - pro automatickou analýzu', 'info');
        this.log('');

        this.log('━'.repeat(79), 'separator');
        this.log('✅ DIAGNOSTIKA DOKONČENA', 'success');
        this.log('━'.repeat(79), 'separator');
    }

    /**
     * Spočítat problémy podle severity
     */
    countIssues() {
        const counts = {
            critical: 0,
            high: 0,
            medium: 0,
            info: 0
        };

        // SQL
        if (this.results.analyze_sql_advanced) {
            const sql = this.results.analyze_sql_advanced;
            counts.high += (sql.missing_indexes?.filter(i => i.priority === 'high')?.length || 0);
            counts.medium += (sql.missing_indexes?.filter(i => i.priority === 'medium')?.length || 0);
            counts.high += (sql.orphaned_records?.length || 0);
            counts.high += (sql.data_integrity_issues?.length || 0);
        }

        // Code Quality
        if (this.results.analyze_code_quality) {
            const code = this.results.analyze_code_quality;
            counts.critical += (code.syntax_issues?.length || 0);
            counts.high += (code.complexity?.filter(c => c.severity === 'high')?.length || 0);
            counts.medium += (code.complexity?.filter(c => c.severity === 'medium')?.length || 0);
            counts.info += (code.dead_code?.length || 0);
            counts.critical += (code.best_practices?.filter(bp => bp.severity === 'critical')?.length || 0);
            counts.high += (code.best_practices?.filter(bp => bp.severity === 'high')?.length || 0);
        }

        // Security
        if (this.results.security_deep_scan) {
            const sec = this.results.security_deep_scan;
            counts.critical += (sec.sql_injection_risks?.length || 0);
            counts.high += (sec.xss_risks?.length || 0);
            counts.high += (sec.csrf_missing?.length || 0);
            counts.critical += (sec.exposed_files?.filter(f => f.severity === 'critical')?.length || 0);
        }

        // Performance
        if (this.results.analyze_performance) {
            const perf = this.results.analyze_performance;
            counts.high += (perf.large_files?.filter(f => f.size_bytes > 2000000)?.length || 0);
            counts.info += (perf.unminified_assets?.length || 0);
        }

        return counts;
    }

    /**
     * Export do JSON
     */
    exportJSON() {
        const json = JSON.stringify(this.results, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `diagnostics_${Date.now()}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }

    /**
     * Export do TXT
     */
    exportTXT() {
        const output = document.getElementById('console-output');
        const text = output.innerText;
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `diagnostics_${Date.now()}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    }

    /**
     * Připravit data pro AI analýzu
     */
    async prepareAIAnalysis() {
        try {
            const response = await fetch('/api/advanced_diagnostics_api.php?action=prepare_ai_data', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.log('');
                this.log('🤖 AI ANALYSIS DATA PREPARED', 'success');
                this.log('─'.repeat(79), 'separator');
                this.log('Data připravena pro odeslání do AI analyzeru', 'info');
                this.log('');
                this.log('Můžete zkopírovat níže uvedená data a vložit do AI:', 'info');
                this.log('');
                this.log(JSON.stringify(data.data, null, 2), 'code');
            }
        } catch (error) {
            this.log(`❌ Chyba při přípravě AI dat: ${error.message}`, 'error');
        }
    }

    /**
     * Logování do konzole
     */
    log(message, className = 'normal') {
        const output = document.getElementById('console-output');
        const line = document.createElement('div');
        line.className = `console-line ${className}`;
        line.textContent = message;
        output.appendChild(line);
        output.scrollTop = output.scrollHeight;
    }
}

// Globální instance
const advancedDiagnostics = new AdvancedDiagnostics();
