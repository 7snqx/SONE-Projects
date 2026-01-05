<?php
header('Content-Type: application/json');

// URL strony z podanymi cenami (tu podaj dokładny adres strony z tym HTML)
$url = 'https://www.e-petrol.pl/';  // zamień na właściwy URL, np. https://www.e-petrol.pl/ceny-paliw

$html = file_get_contents($url);

if ($html === false) {
    echo json_encode(['error' => 'Nie udało się pobrać strony']);
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Znajdź div "tabela"
$tabela = $xpath->query('//div[@id="tabela"]')->item(0);

if (!$tabela) {
    echo json_encode(['error' => 'Nie znaleziono elementu z id tabela']);
    exit;
}

// W XPath szukamy divów o klasie row tab-divs wewnątrz "tabela", które zawierają odpowiedni tekst "Pb95" w pierwszym divie z klasą col
$rows = $xpath->query('.//div[contains(@class,"row") and contains(@class,"tab-divs")]', $tabela);

$prices = [];

foreach ($rows as $row) {
    $cols = $xpath->query('.//div[contains(@class,"col")]', $row);
    if ($cols->length >= 2) {
        $name = trim($cols->item(0)->textContent);
        $value = trim($cols->item(1)->textContent);
        // Filtrujemy tylko typy paliw (pomijamy "Aktualizacja" itp.)
        if (in_array($name, ['Pb98', 'Pb95', 'ON', 'LPG'])) {
            $prices[$name] = floatval(str_replace(',', '.', $value));
        }
    }
}

if (!empty($prices)) {
    $prices['ON+'] = $prices['ON'] + 0.30;
    echo json_encode(['ceny_paliw' => $prices]);
} else {
    echo json_encode(['error' => 'Nie znaleziono cen paliw']);
}
?>