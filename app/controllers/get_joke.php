<?php
/**
 * Generátor vtipů - POKAŽDÉ JINÝ při každém přihlášení
 * Kombinuje external API + lokální databázi vtipů
 */

require_once __DIR__ . '/../../init.php';

// ✅ FIX: HTTP hlavičky pro zabránění cachování
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Získat user info
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? 'Host';

try {
    // Pokus o načtení z JokeAPI (externí API) - VŽDY náhodný
    error_log("get_joke.php: Attempting to fetch from JokeAPI...");
    $joke = fetchFromJokeAPI();

    if ($joke) {
        error_log("get_joke.php: JokeAPI SUCCESS - Joke: " . substr($joke, 0, 50) . "...");
        echo json_encode([
            'status' => 'success',
            'joke' => $joke,
            'source' => 'jokeapi',
            'debug' => 'from_external_api'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        error_log("get_joke.php: JokeAPI returned null, falling back to local");
    }
} catch (Exception $e) {
    error_log("get_joke.php: JokeAPI EXCEPTION: " . $e->getMessage());
}

// Fallback: Použij lokální databázi vtipů - NÁHODNÝ vtip
error_log("get_joke.php: Using local jokes database");
$result = getLocalJoke();
$joke = $result['joke'];
$debug = $result['debug'];

error_log("get_joke.php: LOCAL JOKE selected - Index: " . $debug['selected_index'] . ", Total: " . $debug['total_jokes'] . ", Joke: " . substr($joke, 0, 50) . "...");

echo json_encode([
    'status' => 'success',
    'joke' => $joke,
    'source' => 'local',
    'debug' => $debug
], JSON_UNESCAPED_UNICODE);

/**
 * Získá vtip z JokeAPI.dev - VŽDY náhodný
 */
function fetchFromJokeAPI(): ?string {
    // JokeAPI v2 endpoint s češtinou + timestamp pro zabránění cachování
    $timestamp = time() . rand(1000, 9999); // Extra randomizace
    $url = 'https://v2.jokeapi.dev/joke/Any?lang=cs&type=single&format=json&_=' . $timestamp;

    $context = stream_context_create([
        'http' => [
            'timeout' => 3, // 3 sekundy timeout
            'user_agent' => 'WGS-Service/1.0'
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        error_log('Failed to fetch joke from API: ' . $url);
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['joke'])) {
        return $data['joke'];
    }

    // Pokud má setup+delivery (two-part joke)
    if (isset($data['setup']) && isset($data['delivery'])) {
        return $data['setup'] . "\n\n" . $data['delivery'];
    }

    return null;
}

/**
 * Vrátí NÁHODNÝ vtip z lokální databáze
 */
function getLocalJoke(): array {
    $jokes = [
        // Pracovní humor
        "Proč programátoři nemají rádi přírodu?\nProtože tam je moc bugů! 🐛",
        "Počítač hlásí: 'Klaviatura nenalezena. Stiskněte F1 pro pokračování.' 🤔",
        "Kolik programátorů je potřeba na výměnu žárovky?\nŽádného. Je to hardwarový problém! 💡",
        "Proč je Java tak dlouhá?\nProtože ji psali Jávánci! ☕",

        // Technické
        "IT technik říká: 'Máte zapnutý počítač?'\n'Ano.'\n'A funguje?'\n'Ne.'\n'Tak ho zkuste vypnout a zapnout.' 🔄",
        "Existují jen 10 typů lidí: Ti, kteří rozumí binární soustavě, a ti, kteří ne. 01010",
        "Proč se SQL developerovi nelíbí pláž?\nProtože tam jsou samé NULL values! 🏖️",

        // Kancelářský humor
        "Boss: 'Proč přicházíš pozdě?'\nJá: 'Protože jste mi říkal, abych nepřicházel včas.' ⏰",
        "Práce je jako baterka - vydrží jen do doby, než ji nejvíc potřebuješ. 🔦",
        "Pondělí by mělo být volno. Potřebujem se vzpamatovat z víkendu! 😴",

        // Pozitivní motivační
        "Úspěch není o tom, jak často padáš, ale jak často vstaněš s úsměvem! 💪",
        "Nejlepší čas na start byl včera. Druhý nejlepší čas je TEĎ! 🚀",
        "Káva: protože život je příliš krátký na špatnou náladu! ☕",
        "Každý den je nová příležitost být lepší než včera! ✨",

        // Zákaznický servis
        "Zákazník má vždy pravdu... až do doby než otevře ústa. 😅",
        "Některé problémy se řeší vypnutím a zapnutím.\nU zákazníků to bohužel nefunguje. 🙃",
        "Nejlepší část zákaznického servisu? Tlačítko 'Zavřít ticket'! ✅",

        // České reálie
        "Čech je spokojen, když se má na co stěžovat! 😄",
        "Češi mají zlaté ruce - všechno co se dotknou, musí opravovat! 🔧",
        "V Česku máme čtyři roční období: zima, ještě zima, už zase zima a stavební. 🏗️",

        // Motivační
        "Dnes je krásný den na to být úžasný! 🌟",
        "Usmívej se! Dáváš všem najevo že jsi silnější než včera. 😊",
        "Tvůj jediný limit jsi ty sám. Překroč ho! 🎯",
        "Malé kroky každý den vedou k velkým výsledkům! 👣",

        // Vtip z příkladu uživatele - přidáme ho do databáze!
        "Znám spoustu vtipů ve znakové řeči, které nikdo neslyšel! 🤟😄"
    ];

    $totalJokes = count($jokes);
    error_log("get_joke.php: getLocalJoke() - Total jokes in array: " . $totalJokes);

    // ✅ FIX: JEDNODUŠŠÍ randomizace - jen shuffle a vzít první prvek
    // Toto je spolehlivější než kombinace shuffle + random_int
    shuffle($jokes);

    $selectedJoke = $jokes[0];
    $selectedIndex = 0; // Po shuffle je to vždy první prvek

    error_log("get_joke.php: getLocalJoke() - After shuffle, selected joke: " . substr($selectedJoke, 0, 30) . "...");

    return [
        'joke' => $selectedJoke,
        'debug' => [
            'total_jokes' => $totalJokes,
            'selected_index' => $selectedIndex,
            'method' => 'shuffle_first',
            'timestamp' => time(),
            'microtime' => microtime(true)
        ]
    ];
}
