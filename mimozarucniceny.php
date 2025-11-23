<?php
/**
 * Redirect: mimozarucniceny.php → cenik.php
 *
 * Tato stránka byla sloučena do nového ceníku služeb.
 * Automatické přesměrování na cenik.php#kalkulacka
 */

header('HTTP/1.1 301 Moved Permanently');
header('Location: /cenik.php#kalkulacka');
exit;
?>
