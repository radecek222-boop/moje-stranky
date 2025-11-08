<?php require_once "init.php"; ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ochrana osobních údajů (GDPR) – White Glove Service</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <style>
    .gdpr-content {
      max-width: 900px;
      margin: 0 auto;
      padding: 3rem 2rem;
      font-family: 'Poppins', sans-serif;
      line-height: 1.8;
      color: #333;
    }
    .gdpr-content h2 {
      font-size: 1.5rem;
      font-weight: 500;
      letter-spacing: 0.05em;
      margin-top: 2.5rem;
      margin-bottom: 1rem;
      color: #000;
      border-bottom: 2px solid #000;
      padding-bottom: 0.5rem;
    }
    .gdpr-content h3 {
      font-size: 1.2rem;
      font-weight: 500;
      margin-top: 1.5rem;
      margin-bottom: 0.5rem;
      color: #333;
    }
    .gdpr-content p {
      margin-bottom: 1rem;
      color: #555;
    }
    .gdpr-content ul {
      margin-bottom: 1rem;
      padding-left: 2rem;
    }
    .gdpr-content li {
      margin-bottom: 0.5rem;
      color: #555;
    }
    .gdpr-content strong {
      color: #000;
      font-weight: 600;
    }
    .gdpr-contact {
      margin-top: 2rem;
      padding: 1.5rem;
      background: #f5f5f5;
      border-left: 4px solid #000;
    }
    .gdpr-contact a {
      color: #0066cc;
      text-decoration: underline;
      font-weight: 600;
    }
    .gdpr-contact a:hover {
      color: #004499;
    }
    .gdpr-last-updated {
      margin-top: 3rem;
      padding-top: 1rem;
      border-top: 1px solid #ddd;
      font-size: 0.9rem;
      color: #666;
      text-align: center;
    }
  </style>
</head>
<body>

<main>
<!-- HERO -->
<section class="hero">
  <div>
    <h1 class="hero-title">ZPRACOVÁNÍ OSOBNÍCH ÚDAJŮ (GDPR)</h1>
    <p class="hero-subtitle">Transparentně vysvětlujeme, jaké informace při poskytování servisu shromažďujeme, proč je potřebujeme a jak chráníme vaše práva.</p>
  </div>
</section>

<div class="gdpr-content">
  <p><strong>White Glove Service s.r.o.</strong> respektuje vaše soukromí a chrání vaše osobní údaje v souladu s Nařízením Evropského parlamentu a Rady (EU) 2016/679 o ochraně fyzických osob v souvislosti se zpracováním osobních údajů (GDPR).</p>

  <h2>1. Správce osobních údajů</h2>
  <div class="gdpr-contact">
    <p><strong>White Glove Service s.r.o.</strong></p>
    <p>Email: <a href="mailto:info@wgs-service.cz">info@wgs-service.cz</a></p>
    <p>Web: <a href="https://www.wgs-service.cz">www.wgs-service.cz</a></p>
  </div>

  <h2>2. Jaké osobní údaje zpracováváme</h2>

  <h3>2.1 Údaje při objednávce servisu (reklamaci)</h3>
  <ul>
    <li><strong>Identifikační údaje:</strong> jméno, příjmení, telefonní číslo, email</li>
    <li><strong>Adresní údaje:</strong> adresa místa servisu (ulice, město, PSČ, GPS souřadnice pro navigaci techniků)</li>
    <li><strong>Informace o produktu:</strong> číslo objednávky/reklamace, název produktu, výrobce, prodejce, číslo faktury, datum nákupu, záruka</li>
    <li><strong>Údaje o závadě:</strong> popis závady, typ závady, požadovaný termín servisu</li>
    <li><strong>Fakturační údaje:</strong> označení, zda se jedná o CZ/SK firmu (pro správné fakturování)</li>
    <li><strong>Fotodokumentace:</strong> fotografie a videa poškození produktu nahrané technikem nebo zákazníkem</li>
    <li><strong>Metadata:</strong> IP adresa, časové razítko odeslání formuláře, User-Agent prohlížeče</li>
  </ul>

  <h3>2.2 Údaje registrovaných uživatelů (prodejci, technici)</h3>
  <ul>
    <li><strong>Identifikační údaje:</strong> jméno, příjmení, email, telefonní číslo</li>
    <li><strong>Přihlašovací údaje:</strong> email a zahashované heslo (BCrypt)</li>
    <li><strong>Role v systému:</strong> admin, prodejce, technik</li>
    <li><strong>Údaje o aktivitě:</strong> čas posledního přihlášení, IP adresa přihlášení</li>
  </ul>

  <h3>2.3 Komunikační údaje</h3>
  <ul>
    <li><strong>Emailová komunikace:</strong> korespondence ohledně servisu mezi zákazníkem, technikem, prodejcem a výrobcem</li>
    <li><strong>SMS upozornění:</strong> notifikace o stavu servisu (pokud je tato funkce aktivována)</li>
  </ul>

  <h2>3. Účel zpracování osobních údajů</h2>

  <p>Vaše osobní údaje zpracováváme pro tyto účely:</p>

  <ul>
    <li><strong>Poskytování servisních služeb:</strong> zpracování objednávky servisu, přidělení technika, navigace k místu servisu, vyřízení reklamace</li>
    <li><strong>Komunikace:</strong> kontaktování zákazníka, prodejce, výrobce a technické osoby ohledně průběhu servisu</li>
    <li><strong>Dokumentace:</strong> vedení záznamu o servisu, fotodokumentace závady a opravy, tvorba servisních protokolů</li>
    <li><strong>Fakturace:</strong> vystavení faktury za servisní služby, účetní evidence</li>
    <li><strong>Autentizace uživatelů:</strong> přihlášení do systému, správa přístupu k různým funkcím podle role</li>
    <li><strong>Právní základ:</strong> plnění smlouvy, oprávněný zájem správce, souhlas se zpracováním osobních údajů</li>
  </ul>

  <h2>4. Právní základ zpracování</h2>

  <ul>
    <li><strong>Plnění smlouvy (čl. 6 odst. 1 písm. b) GDPR):</strong> zpracování je nezbytné pro poskytnutí servisních služeb</li>
    <li><strong>Oprávněný zájem (čl. 6 odst. 1 písm. f) GDPR):</strong> zajištění bezpečnosti systému, prevence podvodů, evidence servisu</li>
    <li><strong>Souhlas (čl. 6 odst. 1 písm. a) GDPR):</strong> při objednávce servisu prostřednictvím webového formuláře udělujete souhlas zaškrtnutím políčka</li>
  </ul>

  <h2>5. Doba zpracování osobních údajů</h2>

  <ul>
    <li><strong>Reklamační údaje:</strong> po dobu trvání záruky produktu + 3 roky pro případné právní nároky (dle zákonné záruky)</li>
    <li><strong>Účetní doklady:</strong> 10 let od konce účetního období (dle zákona o účetnictví)</li>
    <li><strong>Fotodokumentace:</strong> po dobu trvání záruky + 3 roky</li>
    <li><strong>Uživatelské účty:</strong> po dobu aktivního používání účtu, po ukončení spolupráce budou údaje anonymizovány nebo smazány</li>
    <li><strong>Metadata (IP adresy, logy):</strong> 90 dní pro bezpečnostní účely</li>
  </ul>

  <h2>6. Příjemci osobních údajů</h2>

  <p>Vaše osobní údaje můžeme předat následujícím příjemcům:</p>

  <ul>
    <li><strong>Technici WGS:</strong> pro navigaci k místu servisu a provedení opravy</li>
    <li><strong>Prodejci a výrobci:</strong> v rámci reklamačního řízení pro vyřízení záruky</li>
    <li><strong>Poskytovatelé IT služeb:</strong> hosting webové aplikace, emailové služby, mapové API (Geoapify pro navigaci)</li>
    <li><strong>Účetní služby:</strong> zpracování fakturace a daňových povinností</li>
  </ul>

  <p>Všichni příjemci jsou vázáni povinností mlčenlivosti a zpracovávají údaje v souladu s GDPR.</p>

  <h2>7. Předávání údajů do třetích zemí</h2>

  <p>Vaše osobní údaje nejsou standardně předávány mimo Evropskou unii. Pokud by k takovému předání došlo (například použití cloudových služeb mimo EU), zajistíme přiměřené záruky ochrany (standardní smluvní doložky, certifikace).</p>

  <h2>8. Vaše práva jako subjektu údajů</h2>

  <p>V souladu s GDPR máte tato práva:</p>

  <ul>
    <li><strong>Právo na přístup (čl. 15 GDPR):</strong> máte právo vědět, jaké vaše osobní údaje zpracováváme</li>
    <li><strong>Právo na opravu (čl. 16 GDPR):</strong> máte právo požadovat opravu nepřesných údajů</li>
    <li><strong>Právo na výmaz (čl. 17 GDPR):</strong> za určitých podmínek máte právo požadovat smazání vašich údajů</li>
    <li><strong>Právo na omezení zpracování (čl. 18 GDPR):</strong> můžete požádat o dočasné omezení zpracování</li>
    <li><strong>Právo na přenositelnost (čl. 20 GDPR):</strong> můžete získat vaše údaje ve strukturovaném formátu a předat je jinému správci</li>
    <li><strong>Právo vznést námitku (čl. 21 GDPR):</strong> můžete vznést námitku proti zpracování na základě oprávněného zájmu</li>
    <li><strong>Právo odvolat souhlas (čl. 7 odst. 3 GDPR):</strong> pokud je zpracování založeno na souhlasu, můžete jej kdykoliv odvolat</li>
    <li><strong>Právo podat stížnost (čl. 77 GDPR):</strong> můžete podat stížnost u Úřadu pro ochranu osobních údajů (<a href="https://www.uoou.cz" target="_blank">www.uoou.cz</a>)</li>
  </ul>

  <h2>9. Bezpečnost osobních údajů</h2>

  <p>Přijali jsme technická a organizační opatření k ochraně vašich osobních údajů:</p>

  <ul>
    <li><strong>Šifrování:</strong> HTTPS pro veškerou komunikaci, BCrypt pro ukládání hesel</li>
    <li><strong>Autentizace:</strong> přístupy k údajům jsou chráněny přihlašovacím systémem s rolemi (admin, prodejce, technik)</li>
    <li><strong>Rate limiting:</strong> ochrana proti zneužití formulářů a brute-force útokům</li>
    <li><strong>Pravidelné aktualizace:</strong> bezpečnostní aktualizace systému a opravy zranitelností</li>
    <li><strong>Omezený přístup:</strong> k údajům mají přístup pouze oprávněné osoby dle jejich role</li>
    <li><strong>Zálohy:</strong> pravidelné zálohy databáze pro případ ztráty dat</li>
  </ul>

  <h2>10. Cookies a sledování</h2>

  <p>Webová aplikace používá <strong>session cookies</strong> pro správu přihlášení uživatelů. Nepoužíváme analytické nebo marketingové cookies třetích stran.</p>

  <h2>11. Kontakt pro záležitosti GDPR</h2>

  <p>Pokud máte dotazy ohledně zpracování vašich osobních údajů nebo chcete uplatnit svá práva, kontaktujte nás:</p>

  <div class="gdpr-contact">
    <p><strong>Email:</strong> <a href="mailto:info@wgs-service.cz">info@wgs-service.cz</a></p>
    <p><strong>Předmět emailu:</strong> GDPR – žádost o informace / výmaz / opravu</p>
    <p>Na vaši žádost odpovíme do <strong>30 dnů</strong> od doručení.</p>
  </div>

  <h2>12. Změny tohoto prohlášení</h2>

  <p>Toto prohlášení můžeme aktualizovat v případě změn v legislativě nebo v našich postupech zpracování údajů. O zásadních změnách vás budeme informovat prostřednictvím emailu nebo oznámení na webové stránce.</p>

  <div class="gdpr-last-updated">
    <p><strong>Poslední aktualizace:</strong> <?php echo date('d. m. Y'); ?></p>
    <p>White Glove Service s.r.o. – Profesionální servis nábytku</p>
  </div>

</div>
</main>

</body>
</html>
