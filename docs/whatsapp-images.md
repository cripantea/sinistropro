# Immagini WhatsApp: rilascio e verifica

Implementazione additiva, nessun cambio a SDK, CORS, signup, invio o inoltro webhook.

## Ordine di rilascio (da autorizzare separatamente)
1. FusionWA: pubblicare il nuovo GET /api/v1/media/{mediaId} e verificarne la salute.
2. SinistroPro: applicare la migration 2026_09_30_000001_add_media_id_to_whatsapp_messages PRIMA di attivare il nuovo codice webhook/job.
3. Attivare backend e asset frontend insieme; riavviare i worker Laravel con la procedura di deploy esistente.
4. Con un numero di test concordato: ricevere una nuova immagine con didascalia, verificare anteprima e apertura; ripetere per echo da telefono in coexistence. Verificare isolamento con altro tenant.
5. Non scollegare/ricollegare numeri o forzare history sync per questa modifica.

## Requisiti e limiti
- FUSIONWA_BASE_URL, FUSIONWA_API_KEY, FUSIONWA_API_SECRET già configurati sul backend; nessun segreto nel browser.
- Sessione FusionWA attiva, tenant identificato con externalCustomerId uguale al tenant ID.
- Il media ID è salvato da messaggi live, echo e history, quando presente nel payload.
- JPEG, PNG e WebP fino a 5 MiB. Audio, video, documenti e sticker non implementati.
- Accesso tramite route autenticata e tenant-scoped; niente URL Meta o token nel frontend.
- Recupero su richiesta, senza archivio persistente. Un allegato scaduto/rimosso da Meta resta non disponibile.
- I messaggi storici già privi di media_id non vengono riparati: il wa_message_id non è il media ID.
- Nessuna risincronizzazione né migrazione dei contenuti esistenti.
- Sessioni legacy non gestite da FusionWA non sono supportate da questo proxy.
- Limiti: 120 richieste/minuto per utente SinistroPro; 120/minuto per applicazione FusionWA.
- L'UI mostra un avviso per allegati assenti o non scaricabili; ricaricare la pagina per ritentare dopo un errore temporaneo.

## Rollback
Ripristinare codice e asset SinistroPro precedenti, lasciando la colonna nullable media_id (compatibile con il vecchio codice).
La nuova route FusionWA può restare senza influire sui client esistenti.
Non eseguire rollback della migration mentre nuovi worker/codice sono attivi; rimuovere la colonna perderebbe i riferimenti acquisiti.

## Verifiche locali
php artisan test
npm run build
Test HTTP con risposte simulate: nessun download Meta reale e nessun invio WhatsApp.
Il collaudo end-to-end con allegato nuovo resta necessario dopo il rilascio.
