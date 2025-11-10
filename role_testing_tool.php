<?php
/**
 * REDIRECT: Role Testing Tool
 * Tento nástroj byl přesunut do Admin panelu
 * Přesměrováváme na admin.php?tab=tools kde je integrováno testování rolí
 */

// Přesměruj na admin panel s kartou tools
header('Location: /admin.php?tab=tools');
exit;
