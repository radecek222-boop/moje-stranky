-- ============================================
-- Migration: Pridani jednoduchych sablon pro pozvanky
-- Datum: 2025-11-28
-- Ucel: Jednoduche sablony pozvanek pro prodejce a techniky
--       pouzivajici stejny system jako ostatni notifikace
-- ============================================

-- Sablona pro prodejce
INSERT INTO wgs_notifications (
    id,
    name,
    description,
    trigger_event,
    recipient_type,
    type,
    subject,
    template,
    variables,
    active
) VALUES (
    'invitation_prodejce',
    'Pozvanka pro prodejce',
    'Email s pozvankou a registracnim klicem pro nove prodejce',
    'invitation_send',
    'seller',
    'email',
    'Pozvanka do systemu WGS - Prodejce',
    'Dobry den,

byli jste pozvani jako prodejce do systemu White Glove Service pro spravu servisnich zakazek Natuzzi.

================================================================================
                         VAS REGISTRACNI KLIC
================================================================================

                              {{registration_key}}

     (Zkopirujte tento klic - budete ho potrebovat pri registraci)

================================================================================
                    JAK SE ZAREGISTROVAT
================================================================================

KROK 1: Otevrete stranku registrace
        {{app_url}}/registration.php

KROK 2: Vyplnte formular
        - Registracni klic: vlozte klic z tohoto emailu
        - Jmeno a prijmeni: vase cele jmeno
        - Email: vase emailova adresa
        - Telefon: vase telefonni cislo
        - Heslo: vymyslete si heslo (min. 12 znaku)

KROK 3: Prihlaste se
        {{app_url}}/login.php

================================================================================
                    CO BUDETE MOCT DELAT V SYSTEMU
================================================================================

  - Zadavat nove reklamace pro vase zakazniky
  - Sledovat stav vasich zakazek v realnem case
  - Videt historii vsech reklamaci ktere jste zadali
  - Nahravat dokumenty a fotky k zakazkam
  - Pridavat poznamky pro techniky
  - Videt kdy technik navstivi zakaznika

================================================================================
                         DULEZITE UPOZORNENI
================================================================================

Registracni klic je urcen pouze pro vas.
Prosim, nesdílejte ho s nikym dalsim.

================================================================================
                    POTREBUJETE POMOC?
================================================================================

Radi vas proskolime po telefonu nebo osobne.
Skoleni je zdarma a trva priblizne 15-30 minut.

Telefon: +420 725 965 826
Email:   reklamace@wgs-service.cz

================================================================================
               White Glove Service - Autorizovany servis Natuzzi
                           www.wgs-service.cz
================================================================================',
    JSON_ARRAY('{{registration_key}}', '{{app_url}}'),
    1
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    subject = VALUES(subject),
    template = VALUES(template),
    variables = VALUES(variables),
    updated_at = NOW();

-- Sablona pro techniky
INSERT INTO wgs_notifications (
    id,
    name,
    description,
    trigger_event,
    recipient_type,
    type,
    subject,
    template,
    variables,
    active
) VALUES (
    'invitation_technik',
    'Pozvanka pro technika',
    'Email s pozvankou a registracnim klicem pro nove techniky',
    'invitation_send',
    'technician',
    'email',
    'Pozvanka do systemu WGS - Servisni technik',
    'Dobry den,

byli jste pozvani jako servisni technik do systemu White Glove Service pro spravu servisnich zakazek Natuzzi.

================================================================================
                         VAS REGISTRACNI KLIC
================================================================================

                              {{registration_key}}

     (Zkopirujte tento klic - budete ho potrebovat pri registraci)

================================================================================
                    JAK SE ZAREGISTROVAT
================================================================================

KROK 1: Otevrete stranku registrace
        {{app_url}}/registration.php

KROK 2: Vyplnte formular
        - Registracni klic: vlozte klic z tohoto emailu
        - Jmeno a prijmeni: vase cele jmeno
        - Email: vase emailova adresa
        - Telefon: vase telefonni cislo
        - Heslo: vymyslete si heslo (min. 12 znaku)

KROK 3: Prihlaste se
        {{app_url}}/login.php

================================================================================
                    CO BUDETE MOCT DELAT V SYSTEMU
================================================================================

  - Videt sve prirazene zakazky v prehlednem seznamu
  - Menit stav zakazky (Ceka / Domluvena / Hotovo)
  - Vyplnovat servisni protokoly s automatickym prekladem
  - Nahravat fotky pred a po oprave
  - Videt adresu zakaznika na mape s navigaci
  - Nechat zakaznika elektronicky podepsat protokol
  - Exportovat protokol do PDF a poslat zakaznikovi

================================================================================
                         DULEZITE UPOZORNENI
================================================================================

Registracni klic je urcen pouze pro vas.
Prosim, nesdílejte ho s nikym dalsim.

================================================================================
                    POTREBUJETE POMOC?
================================================================================

Radi vas proskolime po telefonu nebo osobne.
Skoleni je zdarma a trva priblizne 15-30 minut.

Telefon: +420 725 965 826
Email:   reklamace@wgs-service.cz

================================================================================
               White Glove Service - Autorizovany servis Natuzzi
                           www.wgs-service.cz
================================================================================',
    JSON_ARRAY('{{registration_key}}', '{{app_url}}'),
    1
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    subject = VALUES(subject),
    template = VALUES(template),
    variables = VALUES(variables),
    updated_at = NOW();

-- ============================================
-- Kontrola vysledku
-- ============================================
SELECT id, name, recipient_type, subject FROM wgs_notifications WHERE id LIKE 'invitation_%';
