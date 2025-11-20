<?php
require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=statistiky.php');
    exit;
}

// Embed mode - skrýt navigaci
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statistiky | White Glove Service</title>
  <meta name="description" content="Statistiky a přehledy White Glove Service. Sledujte metriky servisu, úspěšnost oprav, spokojenost zákazníků a výkonnost techniků.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/statistiky.min.css">
  <link rel="stylesheet" href="assets/css/statistiky-fixes.css">
  <link rel="stylesheet" href="assets/css/admin.css">

  <style>
/* Kompaktní statistiky */
.stats-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* User bar - kompaktní */
.stats-user-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
}

.stats-title {
    font-size: 1.2rem;
    font-weight: 500;
    margin: 0;
}

.stats-subtitle {
    font-size: 0.75rem;
    color: #666;
    margin: 0;
}

.stats-user {
    font-size: 0.75rem;
    color: #2D5016;
    font-weight: 600;
}

/* Kompaktní filtry */
.stats-filters {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
}

.stats-filters-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
    color: #666;
}

.stats-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.stats-filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stats-filter-label {
    font-size: 0.65rem;
    color: #666;
    font-weight: 500;
}

.stats-filter-input,
.stats-filter-select {
    padding: 0.4rem 0.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 0.7rem;
    width: 100%;
}

.stats-filter-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.stats-btn {
    padding: 0.4rem 0.8rem;
    border: 1px solid #e0e0e0;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7rem;
    transition: all 0.2s;
}

.stats-btn:hover {
    border-color: #2D5016;
}

.stats-btn-primary {
    background: #2D5016;
    color: white;
    border-color: #2D5016;
}

/* Summary stats - minimalistické */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.4rem;
    margin-bottom: 0.6rem;
}

.stats-summary-card {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-left: 2px solid #2D5016;
    padding: 0.5rem 0.6rem;
    border-radius: 3px;
}

.stats-summary-label {
    font-size: 0.6rem;
    color: #777;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
    letter-spacing: 0.02em;
}

.stats-summary-value {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.1rem;
    color: #2D5016;
}

.stats-summary-sub {
    font-size: 0.6rem;
    color: #999;
}

/* Card Grid - jako Control Center */
.stats-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.stats-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #2D5016;
}

.stats-card-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    color: #2D5016;
}

.stats-card-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #000;
}

.stats-card-description {
    font-size: 0.8rem;
    color: #666;
    line-height: 1.4;
}

/* Tabulky v modalech */
.cc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.cc-table thead {
    background: #f5f5f5;
    border-bottom: 2px solid #2D5016;
}

.cc-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #2D5016;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.05em;
}

.cc-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e0e0e0;
}

.cc-table tbody tr:hover {
    background: #f9f9f9;
}

.cc-table tbody tr:last-child td {
    border-bottom: none;
}
</style>
</head>

<body<?php if ($embedMode): ?> class="embed-mode"<?php endif; ?>>
<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php endif; ?>

<!-- MAIN CONTENT -->
<main<?php if ($embedMode) echo ' style="margin-top: 0; padding-top: 0;"'; ?>>
<div class="stats-container">

  <!-- USER INFO BAR - kompaktní -->
  <div class="stats-user-bar">
    <div>
      <h1 class="stats-title">Statistiky</h1>
      <p class="stats-subtitle">Analýza a reporty reklamací</p>
    </div>
    <div class="stats-user">
      <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Administrátor'); ?>
    </div>
  </div>

  <!-- KOMPAKTNÍ FILTRY -->
  <div class="stats-filters">
    <div class="stats-filters-title">Filtry</div>

    <div class="stats-filters-grid">
      <div class="stats-filter-group">
        <label class="stats-filter-label">Měsíc</label>
        <select class="stats-filter-select" id="filter-month" onchange="handleMonthChange()">
          <option value="">Vlastní rozsah</option>
          <option value="current" selected>Aktuální měsíc</option>
          <option value="last">Minulý měsíc</option>
          <option value="2024-01">Leden 2024</option>
          <option value="2024-02">Únor 2024</option>
          <option value="2024-03">Březen 2024</option>
          <option value="2024-04">Duben 2024</option>
          <option value="2024-05">Květen 2024</option>
          <option value="2024-06">Červen 2024</option>
          <option value="2024-07">Červenec 2024</option>
          <option value="2024-08">Srpen 2024</option>
          <option value="2024-09">Září 2024</option>
          <option value="2024-10">Říjen 2024</option>
          <option value="2024-11">Listopad 2024</option>
          <option value="2024-12">Prosinec 2024</option>
          <option value="2025-01">Leden 2025</option>
          <option value="2025-02">Únor 2025</option>
          <option value="2025-03">Březen 2025</option>
          <option value="2025-04">Duben 2025</option>
          <option value="2025-05">Květen 2025</option>
          <option value="2025-06">Červen 2025</option>
          <option value="2025-07">Červenec 2025</option>
          <option value="2025-08">Srpen 2025</option>
          <option value="2025-09">Září 2025</option>
          <option value="2025-10">Říjen 2025</option>
          <option value="2025-11">Listopad 2025</option>
          <option value="2025-12">Prosinec 2025</option>
        </select>
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Prodejce</label>
        <select class="stats-filter-select" id="filter-salesperson">
          <option value="">Všichni</option>
        </select>
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Země</label>
        <select class="stats-filter-select" id="filter-country">
          <option value="">Všechny</option>
          <option value="CZ">🇨🇿 Česko</option>
          <option value="SK">🇸🇰 Slovensko</option>
        </select>
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Stav</label>
        <select class="stats-filter-select" id="filter-status">
          <option value="">Všechny</option>
          <option value="wait">ČEKÁ</option>
          <option value="open">DOMLUVENÁ</option>
          <option value="done">HOTOVO</option>
        </select>
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Od data</label>
        <input type="date" class="stats-filter-input" id="filter-date-from">
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Do data</label>
        <input type="date" class="stats-filter-input" id="filter-date-to">
      </div>
    </div>

    <div class="stats-filter-actions">
      <button class="stats-btn" onclick="resetFilters()">Reset</button>
      <button class="stats-btn stats-btn-primary" onclick="applyFilters()">Aplikovat</button>
    </div>
  </div>

  <!-- SUMMARY STATISTIKY - kompaktní -->
  <div class="stats-summary">
    <div class="stats-summary-card">
      <div class="stats-summary-label">Celkem zakázek</div>
      <div class="stats-summary-value" id="total-orders">0</div>
      <div class="stats-summary-sub">Filtrovaných</div>
    </div>

    <div class="stats-summary-card">
      <div class="stats-summary-label">Celkový obrat</div>
      <div class="stats-summary-value" id="total-revenue">0 €</div>
      <div class="stats-summary-sub">Všechny zakázky</div>
    </div>

    <div class="stats-summary-card">
      <div class="stats-summary-label">Průměrná zakázka</div>
      <div class="stats-summary-value" id="avg-order">0 €</div>
      <div class="stats-summary-sub">Na zakázku</div>
    </div>

    <div class="stats-summary-card">
      <div class="stats-summary-label">Aktivní technici</div>
      <div class="stats-summary-value" id="active-techs">0</div>
      <div class="stats-summary-sub">V období</div>
    </div>
  </div>

  <!-- CARD GRID S MODALY -->
  <div class="stats-card-grid">

    <!-- Statistiky prodejců -->
    <div class="stats-card" onclick="openStatsModal('salesperson')">
      <div class="stats-card-title">Statistiky prodejců</div>
      <div class="stats-card-description">Detailní přehled výkonnosti prodejců, počet zakázek a obraty</div>
    </div>

    <!-- Statistiky techniků -->
    <div class="stats-card" onclick="openStatsModal('technician')">
      <div class="stats-card-title">Statistiky techniků</div>
      <div class="stats-card-description">Výkonnost techniků, úspěšnost oprav a výdělky</div>
    </div>

    <!-- Nejporuchovější modely -->
    <div class="stats-card" onclick="openStatsModal('models')">
      <div class="stats-card-title">Nejporuchovější modely</div>
      <div class="stats-card-description">Analýza nejčastěji porouchaných modelů a výrobků</div>
    </div>

    <!-- Filtrované zakázky -->
    <div class="stats-card" onclick="openStatsModal('orders')">
      <div class="stats-card-title">Filtrované zakázky</div>
      <div class="stats-card-description">Přehled všech zakázek podle aktuálních filtrů</div>
    </div>

    <!-- Grafy a vizualizace -->
    <div class="stats-card" onclick="openStatsModal('charts')">
      <div class="stats-card-title">Grafy a vizualizace</div>
      <div class="stats-card-description">Grafická analýza rozdělení podle měst, zemí a modelů</div>
    </div>

  </div>

</div>
</main>

<!-- MODAL OVERLAY -->
<div class="cc-modal-overlay" id="statsModalOverlay" onclick="closeStatsModal()">
    <div class="cc-modal" onclick="event.stopPropagation()">
        <div class="cc-modal-header">
            <h2 class="cc-modal-title" id="statsModalTitle">Statistiky</h2>
            <button class="cc-modal-close" onclick="closeStatsModal()">×</button>
        </div>
        <div class="cc-modal-body" id="statsModalBody">
            <!-- Obsah se načte dynamicky -->
        </div>
    </div>
</div>

<!-- jsPDF pro PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script src="assets/js/logger.js" defer></script>
<script src="assets/js/statistiky.js" defer></script>

</body>
</html>
