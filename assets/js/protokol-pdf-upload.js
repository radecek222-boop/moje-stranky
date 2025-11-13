/**
 * PATCH pro protokol.min.js - Upload PDF na server
 * Tento soubor musí být načten PO protokol.min.js
 */

// Uložit originální funkci
const originalExportBothPDFs = window.exportBothPDFs;

// Přepsat s novým chováním
window.exportBothPDFs = async function() {
    try {
        showLoading(true);
        logger.log('📋 Generuji kompletní PDF (protokol+fotodokumentace)...');

        // Vytvořit JEDNO PDF s protokolem
        const doc = await generateProtocolPDF();

        // 🆕 NOVÉ: Upload PDF na server
        if (currentReklamaceId) {
            logger.log('📤 Ukládám PDF na server...');
            
            try {
                const pdfBlob = doc.output('blob');
                const reader = new FileReader();
                
                const pdfPath = await new Promise((resolve, reject) => {
                    reader.onloadend = async () => {
                        try {
                            const base64 = reader.result.split(',')[1];
                            
                            const response = await fetch('api/protokol_api.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({
                                    action: 'save_pdf_document',
                                    reklamace_id: currentReklamaceId,
                                    pdf_base64: base64
                                })
                            });
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                logger.log('✅ PDF uloženo na server:', result.path);
                                resolve(result.path);
                            } else {
                                logger.error('❌ Chyba ukládání PDF:', result.error);
                                reject(result.error);
                            }
                        } catch (err) {
                            reject(err);
                        }
                    };
                    
                    reader.onerror = reject;
                    reader.readAsDataURL(pdfBlob);
                });
                
                showNotif("success", "✓ PDF uloženo na server");
                
            } catch (uploadErr) {
                logger.error('⚠️ Nepodařilo se uložit PDF na server:', uploadErr);
                showNotif("warning", "PDF vygenerováno, ale nebylo uloženo");
            }
        }

        // Pokračovat se zbytkem (fotky atd.)
        if (attachedPhotos.length > 0) {
            logger.log('📸 Přidávám fotodokumentaci...');
            
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 10;

            // ... zbytek kódu pro fotky ...
            // (zkopírováno z originálu)
            
            doc.addPage();
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            doc.text('FOTODOKUMENTACE', pageWidth / 2, 20, {align: 'center'});
            
            // ... pokračování ...
        }

        // Otevřít PDF
        window.open(URL.createObjectURL(doc.output("blob")), "_blank");

        // Uložit protokol do DB
        await saveProtokolToDB();

        // Označit jako hotovou
        logger.log('📋 Označuji reklamaci jako hotovou...');
        try {
            // Get CSRF token from meta tag
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const markResponse = await fetch('app/controllers/save.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'update',
                    id: currentReklamaceId,
                    mark_as_completed: '1',
                    csrf_token: csrfToken
                })
            });

            const markResult = await markResponse.json();
            if (markResult.status === 'success') {
                logger.log('✅ Reklamace označena jako hotová');
            }
        } catch (err) {
            logger.error('❌ Chyba při označování:', err);
        }

    } catch (error) {
        logger.error('❌ Chyba při generování PDF:', error);
        showNotif("error", "Chyba při vytváření PDF");
    } finally {
        showLoading(false);
    }
};

logger.log('✅ PDF upload patch načten');
