# Jak tworzyć artykuły na blog

Instrukcja dla kolejnych artykułów na laseryxtool.pl. Opisuje cały proces: od wyboru tematu, przez napisanie pięciu wersji językowych, po publikację. Artykuł ma pomóc klientowi podjąć decyzję, dać się znaleźć w Google i nadawać się do cytowania przez asystentów AI (GEO).

## 1. Wybór tematu

- Zacznij od pytania, które klient zadaje przed zakupem, a nie od produktu. Dobre tematy odpowiadają na jedno konkretne pytanie: „jaki laser do metalu”, „ile kosztuje laser”, „czy laser w domu jest bezpieczny”.
- Ułóż tematy wzdłuż drogi klienta: wybór rodzaju urządzenia, zastosowania i pomysły, koszty, bezpieczeństwo i przygotowanie pracowni, porównania modeli, pierwsze kroki po zakupie.
- Sprawdź, czy temat nie dubluje istniejącego artykułu (lista niżej). Dwa artykuły na tę samą frazę konkurują ze sobą w wynikach wyszukiwania.
- Wybierz 2–4 produkty, które naturalnie pasują do tematu. Maszyny z zestawu demo Michała (P3, F2 Ultra UV, F2 Ultra, F2, MetalFab) mają pierwszeństwo, bo przy nich artykuł może zaprosić na pokaz.
- Ustal główną frazę w każdym języku osobno: tak, jak ludzie naprawdę szukają w danym kraju (np. PL „grawerowanie metalu laserem”, DE „Metall lasergravieren”, RU „лазерная гравировка металла”, UK „лазерне гравіювання металу”).

Opublikowane artykuły (klucz — temat):

| Klucz | Temat |
|---|---|
| `laser-types` | Jaki laser wybrać: diodowy, CO₂, MOPA czy UV |
| `laser-business` | 10 pomysłów na produkty z lasera |
| `laser-welding` | Spawanie laserowe w małym warsztacie |
| `laser-metal` | Grawerowanie metalu: MOPA, światłowód czy dioda |
| `laser-cost` | Ile kosztuje laser: cena, koszty eksploatacji, zwrot |
| `laser-safety` | Bezpieczna praca z laserem: klasy, dym, okulary, pożar |
| `dtf-printing` | Druk DTF i UV na odzieży i gadżetach |

Dopisz tu każdy nowy artykuł.

## 2. Fakty

- Dane techniczne (moce, prędkości, pola robocze, kamery, zabezpieczenia, co jest w zestawie) bierz wyłącznie z danych produktu xTool: `https://www.xtool.eu/products.json?limit=250&page=1` (i kolejne strony; pola `id`, `title`, `body_html`, `variants`). Zapisz kopię stron w folderze roboczym i czytaj ją skryptem.
- Wiedza ogólna jest dopuszczalna, gdy jest powszechnie potwierdzona (długości fal, zasada działania DTF, klasy laserów wg IEC 60825-1, materiały, których nie wolno ciąć laserem, np. PVC).
- Nie wymyślaj liczb, testów, statystyk, opinii klientów, terminów dostaw ani warunków gwarancji. Jeżeli liczba jest niepewna, pomiń ją.
- Nie podawaj cen w tekście. Karty produktów pokazują aktualne ceny, które synchronizacja odświeża co godzinę. Wyjątek: przedziały cen klas urządzeń w artykule o kosztach, zaokrąglone i opatrzone datą.
- Nie przypisuj firmie rzeczy, których nie wiesz (np. gdzie stoi zestaw demo). Pisz „w naszym zestawie demonstracyjnym”, a nie „w naszej pracowni w …”.
- Fizykę sprawdzaj dwa razy. Przykład z artykułu o metalu: metale nie „pochłaniają dobrze” 1064 nm. Laser światłowodowy znakuje je dzięki krótkim impulsom o dużej mocy szczytowej.

## 3. Struktura (SEO i GEO)

- Pierwsze 2–3 zdania to bezpośrednia odpowiedź na główne pytanie. Ten fragment najczęściej cytują Google i asystenci AI.
- Nagłówki H2 odpowiadają prawdziwym pytaniom klienta; z nich powstaje spis treści. H3 dzielą dłuższe sekcje.
- Krótkie akapity, jedna tabela porównawcza i jedna lista tam, gdzie porządkuje treść. Tabela ma najwyżej 5 kolumn i krótkie komórki; na telefonie szersze tabele przewijają się w bok.
- Sekcja „Najczęstsze pytania” zawiera 4–6 pytań H3, a każdą odpowiedź (2–4 zdania) da się zrozumieć bez reszty tekstu.
- Pisz konkretnie i uczciwie: czego urządzenie nie zrobi, jakie są kompromisy, kiedy lepszy jest inny model.
- Karty produktów wstawiaj w 2–4 miejscach, zaraz po akapicie, który o nich mówi (najwyżej 3 produkty w jednym bloku).
- Zakończ krótkim zaproszeniem na bezpłatne demo online. Przycisk demo strona dodaje sama, więc nie wpisuj adresu.
- Długość: około 1100–1500 słów po polsku, w innych językach podobnie.
- Tytuł ma 50–65 znaków i zawiera główną frazę, bez clickbaitu. Zajawka (opis w wynikach wyszukiwania) ma 140–160 znaków i jest pełnym zdaniem z frazą i korzyścią.

## 4. Wersje językowe

- Każda wersja (pl, en, de, ru, uk) to naturalna adaptacja dla danego rynku, a nie dosłowne tłumaczenie. Struktura, fakty i bloki produktów pozostają takie same.
- Jednostki zapisuj ze spacją (60 W, 1064 nm, 914 × 457 mm). W pl, de, ru i uk stosuj przecinek dziesiętny, w en kropkę.
- Cudzysłowy: PL „…”, DE „…“, RU/UK «…», EN “…”.
- Slug: małe litery ASCII z myślnikami, 3–6 słów z główną frazą. RU i UK zapisuj transliteracją (np. `lazerna-gravirovka-metalla`). Slug musi być inny niż w istniejących artykułach.

## 5. Plik artykułu

Każdy artykuł ma osobny plik `scripts/blog/articles/<klucz>.php` (wzór: `articles/laser-metal.php`). Pierwsze trzy artykuły są w `articles.php`.

```php
<?php
/** Jedno zdanie o temacie. Product facts come from the xTool product data. */
return [
    'cover'     => 'cover-<klucz>.webp',
    'days_ago'  => 0,
    'languages' => [
        'pl' => [ 'title' => '…', 'slug' => '…', 'excerpt' => '…', 'blocks' => [ … ] ],
        'en' => [ … ], 'de' => [ … ], 'ru' => [ … ], 'uk' => [ … ],
    ],
];
```

Bloki to zwykły tekst, który skrypt koduje do HTML, więc nie wstawiaj znaczników, linków ani markdownu:

- `[ 'p', 'Akapit.' ]`
- `[ 'h2', 'Nagłówek' ]`, `[ 'h3', 'Podtytuł' ]`
- `[ 'ul', [ 'punkt', 'punkt' ] ]`
- `[ 'table', [ 'Nagłówek 1', 'Nagłówek 2' ], [ [ 'komórka', 'komórka' ], … ] ]`
- `[ 'products', '<ID xTool>,<ID xTool>' ]` — karty produktów z aktualną ceną

Napisy w PHP ujmuj w pojedyncze cudzysłowy, a apostrofy zapisuj jako `\'`.

## 6. Okładka

1600 × 900 px, WebP, bez tekstu, dzięki czemu jedna okładka służy wszystkim językom. Tworzy ją `make_covers.py` z packshotów produktów na białym tle. Pierwsze zdjęcie produktu z danych xTool zapisz w `src/`, dopisz wywołanie `cover(...)` z wysokościami i pozycjami, uruchom skrypt i obejrzyj wynik. Produkty nie powinny na siebie nachodzić. Gotowy plik trafia do `covers/`.

## 7. Praca z asystentem AI

Każdy artykuł z pięcioma wersjami językowymi może pisać osobny agent. Daj mu brief z zasadami z punktów 2–5 i wskaż jako wzór styl i format istniejącego artykułu. Dołącz listę produktów (ID, nazwa, rola), kopię `products.json` i temat z główną frazą PL, sekcjami do omówienia i pytaniami do FAQ. Zażądaj w raporcie listy wszystkich twierdzeń z liczbami wraz ze źródłem (ID produktu albo „wiedza ogólna”) i listy wątpliwości. Raport i polską wersję przeczytaj sam, zanim artykuł trafi do repozytorium.

## 8. Sprawdzenie i publikacja

1. Składnia i struktura: `php -l scripts/blog/articles/<klucz>.php`, a potem wczytanie pliku i wypisanie dla każdego języka liczby bloków oraz długości tytułu i zajawki.
2. Przeczytaj całą wersję polską i sprawdź każde twierdzenie z liczbą w danych produktu. W pozostałych językach porównaj liczby, nazwy i bloki produktów z wersją polską.
3. Lokalnie uruchom `wp eval-file scripts/seed-blog.php`. Skrypt pomija istniejące artykuły, więc można go puszczać wielokrotnie. Potem obejrzyj artykuł na telefonie i komputerze i uruchom test bloga.
4. Commit, PR, recenzja i scalenie.
5. Na produkcji: kopia zapasowa bazy, rsync `scripts`, `wp eval-file scripts/seed-blog.php`, `wp litespeed-purge all`. Na koniec sprawdź artykuł we wszystkich językach.

Po publikacji artykuły edytuje się w panelu WordPress (**Wpisy**). Skrypt seed nie nadpisuje już istniejących artykułów, więc poprawki z panelu zostają.
