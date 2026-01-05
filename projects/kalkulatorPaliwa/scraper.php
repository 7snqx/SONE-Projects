<?php
header('Content-Type: application/json');

// URL strony z podanymi cenami (tu podaj dokładny adres strony z tym HTML)
$exampleUrl = 'https://www.e-petrol.pl/';  // zamień na właściwy URL, np. https://www.e-petrol.pl/ceny-paliw

$exampleHtml = file_get_contents($exampleUrl);

if ($exampleHtml === false) {
    echo json_encode(['error' => 'Nie udało się pobrać strony']);
    exit;
}

libxml_use_internal_errors(true);
$exampleDom = new DOMDocument();
$exampleDom->loadHTML(mb_convert_encoding($exampleHtml, 'HTML-ENTITIES', 'UTF-8'));
libxml_clear_errors();

$exampleXpath = new DOMXPath($exampleDom);

// Znajdź div "tabela"
$exampleTabela = $exampleXpath->query('//div[@id="tabela"]')->item(0);

if (!$exampleTabela) {
    echo json_encode(['error' => 'Nie znaleziono elementu z id tabela']);
    exit;
}

// W XPath szukamy divów o klasie row tab-divs wewnątrz "tabela", które zawierają odpowiedni tekst "Pb95" w pierwszym divie z klasą col
$exampleRows = $exampleXpath->query('.//div[contains(@class,"row") and contains(@class,"tab-divs")]', $exampleTabela);

$examplePrices = [];

foreach ($exampleRows as $exampleRow) {
    $exampleCols = $exampleXpath->query('.//div[contains(@class,"col")]', $exampleRow);
    if ($exampleCols->length >= 2) {
        $exampleName = trim($exampleCols->item(0)->textContent);
        $exampleValue = trim($exampleCols->item(1)->textContent);
        // Filtrujemy tylko typy paliw (pomijamy "Aktualizacja" itp.)
        if (in_array($exampleName, ['Pb98', 'Pb95', 'ON', 'LPG'])) {
            $examplePrices[$exampleName] = floatval(str_replace(',', '.', $exampleValue));
        }
    }
}

if (!empty($examplePrices)) {
    $examplePrices['ON+'] = $examplePrices['ON'] + 0.30;
    echo json_encode(['ceny_paliw' => $examplePrices]);
} else {
    echo json_encode(['error' => 'Nie znaleziono cen paliw']);
}
?>