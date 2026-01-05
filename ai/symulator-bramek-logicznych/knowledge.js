const KNOWLEDGE_BASE = [
    // --- Bramki Podstawowe ---
    {
        keywords: ['and', 'koniunkcja', 'iloczyn', 'i'],
        title: 'Bramka AND (Koniunkcja)',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>AND</strong> to logiczny "iloczyn". Wyjście jest w stanie wysokim (1) <strong>tylko wtedy</strong>, gdy wszystkie wejścia są w stanie wysokim (1).</p>
                <div class="bg-slate-100 dark:bg-slate-700 p-2 rounded text-xs font-mono">
                    <strong>Tabela Prawdy:</strong><br>
                    A | B | OUT<br>
                    --+---+---<br>
                    0 | 0 | 0<br>
                    0 | 1 | 0<br>
                    1 | 0 | 0<br>
                    1 | 1 | 1
                </div>
                <p class="text-xs text-slate-500">Symbol: Prostokąt z zaokrąglonym prawym bokiem (w standardzie USA) lub prostokąt z symbolem "&" (w standardzie IEC).</p>
            </div>
        `
    },
    {
        keywords: ['or', 'alternatywa', 'suma', 'lub'],
        title: 'Bramka OR (Alternatywa)',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>OR</strong> to logiczna "suma". Wyjście jest w stanie wysokim (1), jeśli <strong>przynajmniej jedno</strong> wejście jest w stanie wysokim.</p>
                <div class="bg-slate-100 dark:bg-slate-700 p-2 rounded text-xs font-mono">
                    <strong>Tabela Prawdy:</strong><br>
                    A | B | OUT<br>
                    --+---+---<br>
                    0 | 0 | 0<br>
                    0 | 1 | 1<br>
                    1 | 0 | 1<br>
                    1 | 1 | 1
                </div>
            </div>
        `
    },
    {
        keywords: ['not', 'negacja', 'zaprzeczenie', 'odwracacz', 'nie'],
        title: 'Bramka NOT (Negacja)',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>NOT</strong> (inwerter) ma jedno wejście i jedno wyjście. Zmienia stan na przeciwny.</p>
                <div class="bg-slate-100 dark:bg-slate-700 p-2 rounded text-xs font-mono">
                    IN | OUT<br>
                    ---+---<br>
                    0  | 1<br>
                    1  | 0
                </div>
            </div>
        `
    },
    
    // --- Bramki Rozszerzone ---
    {
        keywords: ['nand'],
        title: 'Bramka NAND',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>NAND</strong> (Not AND) to odwrotność bramki AND. Daje 0 tylko wtedy, gdy wszystkie wejścia to 1.</p>
                <p>Jest to tzw. <em>bramka uniwersalna</em> - używając tylko bramek NAND można zbudować dowolny inny układ logiczny (nawet procesor!).</p>
                <div class="bg-slate-100 dark:bg-slate-700 p-2 rounded text-xs font-mono">
                    A | B | OUT<br>
                    --+---+---<br>
                    0 | 0 | 1<br>
                    0 | 1 | 1<br>
                    1 | 0 | 1<br>
                    1 | 1 | 0
                </div>
            </div>
        `
    },
    {
        keywords: ['nor'],
        title: 'Bramka NOR',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>NOR</strong> (Not OR) to odwrotność bramki OR. Daje 1 tylko wtedy, gdy wszystkie wejścia to 0.</p>
                <p>Podobnie jak NAND, jest to bramka uniwersalna.</p>
                <div class="bg-slate-100 dark:bg-slate-700 p-2 rounded text-xs font-mono">
                    A | B | OUT<br>
                    --+---+---<br>
                    0 | 0 | 1<br>
                    0 | 1 | 0<br>
                    1 | 0 | 0<br>
                    1 | 1 | 0
                </div>
            </div>
        `
    },
    {
        keywords: ['xor', 'różnica', 'exclusive', 'wyłączne'],
        title: 'Bramka XOR (Exclusive OR)',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>XOR</strong> (Alternatywa Rozłączna) daje 1, gdy wejścia są <strong>różne</strong> od siebie.</p>
                <p>Jest kluczowa w operacjach dodawania (sumuje bity bez przeniesienia).</p>
                <div class="bg-slate-100 dark:bg-slate-700 p-2 rounded text-xs font-mono">
                    A | B | OUT<br>
                    --+---+---<br>
                    0 | 0 | 0<br>
                    0 | 1 | 1<br>
                    1 | 0 | 1<br>
                    1 | 1 | 0
                </div>
            </div>
        `
    },
    {
        keywords: ['xnor', 'równoważność'],
        title: 'Bramka XNOR',
        content: `
            <div class="space-y-2">
                <p>Bramka <strong>XNOR</strong> to odwrotność XOR. Daje 1, gdy wejścia są <strong>takie same</strong> (obie 0 lub obie 1).</p>
                <p>Nazywana jest bramką równoważności.</p>
            </div>
        `
    },

    // --- Komponenty Złożone ---
    {
        keywords: ['half', 'adder', 'półsumator', 'polsumator', 'ha'],
        title: 'Półsumator (Half Adder)',
        content: `
            <p>Najprostszy układ arytmetyczny. Dodaje dwa bity A i B.</p>
            <ul class="list-disc list-inside text-xs mt-2">
                <li><strong>S (Sum)</strong>: Wynik dodawania (realizowany przez XOR).</li>
                <li><strong>C (Carry)</strong>: Przeniesienie (realizowane przez AND).</li>
            </ul>
            <p class="mt-2 text-xs">Wzór: 1 + 1 = 0 (i 1 dalej).</p>
        `
    },
    {
        keywords: ['full', 'adder', 'sumator', 'pełny', 'fa'],
        title: 'Sumator Pełny (Full Adder)',
        content: `
            <p>Rozszerzenie półsumatora. Dodaje trzy bity: A, B oraz Cin (Carry In - przeniesienie z poprzedniej pozycji).</p>
            <p>Umożliwia łączenie wielu sumatorów w łańcuch, aby dodawać liczby wielobitowe (np. 8-bitowe, 64-bitowe).</p>
        `
    },
    {
        keywords: ['mux', 'multiplexer', 'multiplekser'],
        title: 'Multiplekser (MUX)',
        content: `
            <p>Działa jak sterowany przełącznik. Posiada wiele wejść danych i jedno wyjście.</p>
            <p>Wejścia sterujące (S0, S1...) decydują, który sygnał wejściowy zostanie "przepuszczony" na wyjście.</p>
            <p class="text-xs mt-1">Przykład: W MUX 4:1, jeśli S=0 (binarnie 00), na wyjście trafia wejście I0. Jeśli S=3 (11), trafia I3.</p>
        `
    },
    {
        keywords: ['demux', 'demultiplexer', 'demultiplekser'],
        title: 'Demultiplekser (DEMUX)',
        content: `
            <p>Odwrotność multipleksera. Rozdziela jeden sygnał wejściowy na jedno z wielu wyjść.</p>
            <p>Adres (S0, S1) decyduje, na które wyjście trafi sygnał z wejścia.</p>
        `
    },
    {
        keywords: ['alu', 'jednostka', 'arytmetyczna', 'logiczna'],
        title: 'ALU (Arithmetic Logic Unit)',
        content: `
            <p>Kluczowy element procesora. Wykonuje operacje matematyczne (dodawanie, odejmowanie) i logiczne (AND, OR, XOR) na liczbach binarnych.</p>
            <p>W tym symulatorze ALU 4-bitowe obsługuje operacje sterowane wejściami Op0 i Op1.</p>
        `
    },
    {
        keywords: ['cpu', 'procesor', 'jednostka', 'centralna'],
        title: 'CPU 4-bit (Procesor)',
        content: `
            <p>Uproszczony model komputera. Wykonuje program zapisany w pamięci.</p>
            <ul class="list-disc list-inside text-xs mt-2">
                <li><strong>PC (Program Counter)</strong>: Wskazuje adres aktualnej instrukcji.</li>
                <li><strong>ACC (Accumulator)</strong>: Rejestr przechowujący wyniki obliczeń.</li>
                <li><strong>Fetch-Decode-Execute</strong>: Cykl pracy procesora.</li>
            </ul>
        `
    },

    // --- Pamięć i Sekwencyjne ---
    {
        keywords: ['flip', 'flop', 'przerzutnik', 'd-ff', 'jk-ff', 'pamięć', 'bit'],
        title: 'Przerzutniki (Flip-Flops)',
        content: `
            <p>Podstawowe elementy pamięci. Potrafią przechowywać 1 bit informacji.</p>
            <ul class="list-disc list-inside text-xs mt-2">
                <li><strong>D-FF</strong>: "Data Flip-Flop". Kopiuje wejście D na wyjście Q w momencie zbocza zegara.</li>
                <li><strong>JK-FF</strong>: Bardziej zaawansowany. Może działać jak D-FF, ale też przełączać stan (Toggle) gdy J=1 i K=1.</li>
            </ul>
        `
    },
    {
        keywords: ['ram', 'pamięć', 'operacyjna'],
        title: 'Pamięć RAM',
        content: `
            <p>Pamięć o dostępie swobodnym (Random Access Memory). Służy do zapisu i odczytu danych w trakcie działania układu.</p>
            <p>Jest ulotna - traci dane po wyłączeniu zasilania (w symulatorze po odświeżeniu strony, chyba że zapiszesz projekt).</p>
        `
    },
    {
        keywords: ['rom', 'pamięć', 'stała', 'program'],
        title: 'Pamięć ROM',
        content: `
            <p>Pamięć tylko do odczytu (Read-Only Memory). Przechowuje stałe dane lub program dla CPU.</p>
            <p class="text-xs mt-1 text-primary">Wskazówka: Kliknij dwukrotnie na moduł ROM w symulatorze, aby edytować jego zawartość (wpisując wartości HEX).</p>
        `
    },
    {
        keywords: ['zegar', 'clock', 'taktowanie', 'częstotliwość', 'hz'],
        title: 'Zegar (Clock)',
        content: `
            <p>Serce układów sekwencyjnych. Generuje sygnał zmieniający się cyklicznie 0-1-0-1.</p>
            <p>Synchronizuje działanie procesora, liczników i przerzutników. Zmiany w tych układach zachodzą zazwyczaj na "zboczu narastającym" (gdy zegar zmienia się z 0 na 1).</p>
        `
    },
    {
        keywords: ['licznik', 'counter', 'cnt'],
        title: 'Licznik (Counter)',
        content: `
            <p>Układ sekwencyjny, który zlicza impulsy zegarowe. Wartość na wyjściu zwiększa się o 1 przy każdym cyklu zegara.</p>
            <p>Może służyć do odmierzania czasu, adresowania pamięci lub jako dzielnik częstotliwości.</p>
        `
    },
    {
        keywords: ['rejestr', 'register', 'reg'],
        title: 'Rejestr',
        content: `
            <p>Grupa przerzutników (zazwyczaj typu D) połączona razem, służąca do przechowywania wielu bitów (np. 4-bitowe słowo).</p>
            <p>Posiada wejście Enable (E), które decyduje, kiedy rejestr ma przyjąć nowe dane.</p>
        `
    },

    // --- Obsługa Symulatora ---
    {
        keywords: ['połączyć', 'łączenie', 'kabel', 'przewód', 'drut', 'linia'],
        title: 'Jak łączyć elementy?',
        content: `
            <ol class="list-decimal list-inside text-xs space-y-1">
                <li>Najedź myszką na port wyjściowy (z prawej strony elementu).</li>
                <li>Wciśnij i przytrzymaj lewy przycisk myszy.</li>
                <li>Przeciągnij linię do portu wejściowego innego elementu.</li>
                <li>Puść przycisk myszy.</li>
            </ol>
            <p class="text-xs mt-2">Możesz też klikać: Kliknij raz na start, potem raz na koniec.</p>
        `
    },
    {
        keywords: ['usunąć', 'kasowanie', 'kosz', 'delete', 'wyczyść'],
        title: 'Usuwanie elementów',
        content: `
            <p><strong>Elementy:</strong> Najedź kursorem na bramkę. W rogu pojawi się mała ikona kosza - kliknij ją.</p>
            <p><strong>Połączenia:</strong> Kliknij bezpośrednio na linię (przewód), aby ją usunąć.</p>
            <p><strong>Wszystko:</strong> Użyj przycisku "Wyczyść" w górnym menu.</p>
        `
    },
    {
        keywords: ['oscyloskop', 'wykres', 'sonda', 'probe', 'analiza'],
        title: 'Używanie Oscyloskopu',
        content: `
            <p>Oscyloskop pozwala widzieć historię sygnału.</p>
            <ol class="list-decimal list-inside text-xs space-y-1">
                <li>Przeciągnij element <strong>SONDA (PROBE)</strong> z menu "Źródła".</li>
                <li>Podłącz sondę do dowolnego wyjścia w układzie.</li>
                <li>Rozwiń panel oscyloskopu na dole ekranu (strzałka w górę).</li>
                <li>Zobaczysz przebieg sygnału w czasie.</li>
            </ol>
        `
    },
    {
        keywords: ['test', 'sygnał', 'animacja', 'kropki', 'przepływ'],
        title: 'Wizualizacja Sygnału',
        content: `
            <p>Przycisk <strong>"Testuj Sygnał"</strong> włącza animację przepływu prądu.</p>
            <p>Żółte poruszające się punkty pokazują, w których przewodach jest stan wysoki (logiczna jedynka).</p>
            <p>To świetne narzędzie do debugowania układu!</p>
        `
    },
    {
        keywords: ['zapis', 'odczyt', 'plik', 'save', 'load', 'projekt'],
        title: 'Zapisywanie i Wczytywanie',
        content: `
            <p>Swoją pracę możesz zapisać na dysku komputera jako plik <code>.json</code>.</p>
            <p>Użyj przycisków "Zapisz" i "Wczytaj" w górnym pasku narzędzi.</p>
        `
    },
    {
        keywords: ['input', 'wejście', 'przełącznik', '0', '1'],
        title: 'Element INPUT',
        content: `
            <p>Element INPUT to źródło sygnału. Kliknij na niego, aby zmienić jego stan z 0 na 1 i odwrotnie.</p>
            <p>Służy do sterowania układem.</p>
        `
    },
    {
        keywords: ['output', 'wyjście', 'dioda', 'led'],
        title: 'Element OUTPUT',
        content: `
            <p>Element OUTPUT służy do podglądu wyniku. Świeci się na zielono, gdy otrzyma sygnał 1.</p>
        `
    },
    {
        keywords: ['hex', 'wyświetlacz', 'siedmiosegmentowy', '7-segment'],
        title: 'Wyświetlacz HEX',
        content: `
            <p>Wyświetla cyfrę szesnastkową (0-9, A-F) na podstawie 4-bitowego wejścia.</p>
            <p>Wejścia mają wagi: 8, 4, 2, 1 (od góry do dołu lub opisane na porcie).</p>
        `
    },
    {
        keywords: ['bufor', 'kontroler', 'buffer'],
        title: 'Bufor / Kontroler',
        content: `
            <p>Bramka, która nie zmienia wartości logicznej (0->0, 1->1).</p>
            <p>Służy do wzmacniania sygnału, izolowania części układu lub jako element opóźniający.</p>
            <p>W tym symulatorze może służyć jako czytelny punkt kontrolny.</p>
        `
    }
];

class Chatbot {
    constructor() {
        this.history = [];
    }

    findAnswer(query) {
        const normalizedQuery = query.toLowerCase();
        // Tokenizacja: dzielimy po znakach niebędących literami/cyframi (uwzględniając polskie znaki)
        const words = normalizedQuery.split(/[^a-z0-9ąęćłńóśźż]+/);
        
        let bestMatch = null;
        let maxScore = 0;

        KNOWLEDGE_BASE.forEach(topic => {
            let score = 0;
            topic.keywords.forEach(keyword => {
                const lowerKeyword = keyword.toLowerCase();
                
                // 1. Dokładne dopasowanie słowa (Najwyższy priorytet)
                // Rozwiązuje problem XOR vs OR (słowo "xor" != "or")
                if (words.includes(lowerKeyword)) {
                    score += 10;
                }
                
                // 2. Dopasowanie frazy (dla słów kluczowych wieloczłonowych np. "half adder")
                if (lowerKeyword.includes(' ') && normalizedQuery.includes(lowerKeyword)) {
                    score += 15;
                }

                // 3. Częściowe dopasowanie (Niski priorytet, tylko dla dłuższych słów)
                // Zapobiega łapaniu "or" wewnątrz "xor" dla krótkich słów, ale pozwala na odmianę słów
                words.forEach(w => {
                    // Sprawdzamy czy słowo użytkownika zawiera słowo kluczowe lub odwrotnie
                    // Ale tylko jeśli słowo kluczowe jest dłuższe niż 3 znaki (żeby nie łapać 'i', 'or', 'not' przypadkiem)
                    if (lowerKeyword.length > 3 && w.length > 3) {
                        if (w.includes(lowerKeyword) || lowerKeyword.includes(w)) {
                            score += 2;
                        }
                    }
                });
            });

            if (score > maxScore) {
                maxScore = score;
                bestMatch = topic;
            }
        });

        // Próg pewności - jeśli wynik jest zbyt niski, nie zgaduj
        if (maxScore >= 5) {
            return bestMatch;
        }
        
        return null;
    }

    getResponse(query) {
        // Proste powitania
        if (query.match(/^(cześć|hej|witaj|dzień dobry|siema)/i)) {
            return {
                title: 'Witaj w Symulatorze!',
                content: 'Cześć! Jestem Twoim asystentem AI. Pomogę Ci zrozumieć działanie bramek logicznych, budowę procesora oraz obsługę tego narzędzia. O co chcesz zapytać?'
            };
        }

        // Pytania o "co to jest", "jak działa" itp.
        // Usuwamy te frazy, żeby nie zakłócały wyszukiwania słów kluczowych, 
        // chociaż nowy algorytm punktacji powinien sobie z tym poradzić.
        
        const answer = this.findAnswer(query);
        
        if (answer) {
            return answer;
        } else {
            // Fallback - próba podpowiedzi
            return {
                title: 'Nie jestem pewien...',
                content: `
                    <p>Nie znalazłem dokładnej odpowiedzi w mojej bazie wiedzy.</p>
                    <p class="text-xs mt-2">Spróbuj zapytać inaczej, np.:</p>
                    <ul class="list-disc list-inside text-xs text-slate-500">
                        <li>"Jak działa bramka XOR?"</li>
                        <li>"Co to jest ALU?"</li>
                        <li>"Jak połączyć elementy?"</li>
                        <li>"Do czego służy zegar?"</li>
                    </ul>
                `
            };
        }
    }
}
