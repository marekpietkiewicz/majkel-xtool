# laseryxtool.pl

Projekt witryny o produktach xTool na WordPressie i hostingu Hostido. Motyw prezentuje katalog, rezerwację demonstracji i formularz zgłoszenia zamówienia. Wtyczka importuje dane produktów z xTool.

Kod znajduje się w `wp-content/themes/lasery-xtool` i `wp-content/plugins/lasery-xtool`. [Instrukcja importu](wp-content/plugins/lasery-xtool/README.md) opisuje aktualny zakres i ograniczenia wtyczki.

## Wygląd i biblioteki

Motyw jest projektowany od widoku mobilnego, w jasnej stylistyce zbliżonej do xtool.eu. Zdjęcia produktów na białym tle są wtapiane w jasne tła (`mix-blend-mode: multiply`), dlatego element ze zdjęciem i jego tło muszą być w tym samym kontekście warstw (np. tło na slajdzie Swipera, a nie na sekcji).

- [Swiper 14.3.0](https://swiperjs.com) (MIT) — karuzela w hero (mysz, dotyk, klawiatura, autoplay z pauzą), karuzele akcesoriów i galeria produktu. Plik JS w `assets/lib/swiper`; potrzebne reguły CSS są w `style.css` w płaskiej składni, bo arkusz Swipera 14 wymaga zagnieżdżania CSS, którego nie obsługują starsze Safari.
- [PhotoSwipe 5.4.4](https://photoswipe.com) (MIT) — powiększanie zdjęć w galerii, ładowany jako moduł JS tylko na stronach produktów.
- Kroje Geologica (nagłówki) i Manrope (tekst), SIL OFL 1.1, hostowane lokalnie w `assets/fonts` (łaciński, łaciński rozszerzony i cyrylica). Nie są pobierane z Google Fonts.

Biblioteki aktualizuje się przez podmianę plików w `assets/lib` i numeru wersji w `functions.php`.

Treści strony głównej (hero, zalety, akcesoria, kroki demo, stopka) i dane firmy edytuje się w **Wygląd → Personalizacja → Treść strony xTool**.

## Blog

Lista artykułów jest na stronie o adresie `blog` (szablon `page-blog.php`, stronicowanie `?strona=2`), a artykuły mają adresy `/blog/<slug>/?lang=<język>` (struktura bezpośrednich odnośników `/blog/%postname%/`). Każda wersja językowa jest osobnym wpisem. W edycji wpisu ramka **Język i tłumaczenia** ustawia język i tworzy szkice tłumaczeń z kopią treści oryginału. Każdy język może mieć tylko jedną wersję artykułu, a po trwałym usunięciu oryginału jego rolę przejmuje najstarsze tłumaczenie. Lista, kanał RSS i archiwa pokazują artykuły w języku interfejsu.

Karty produktów w treści wstawia blok „Shortcode” z `[lasery_produkty xtool="<identyfikatory xTool>"]` lub `[lasery_produkty ids="<ID wpisów>"]` (do sześciu produktów, w podanej kolejności). Teksty listy i ramki pod artykułem edytuje się w **Wygląd → Personalizacja → Treść strony xTool**. Motyw nie wyświetla komentarzy, więc są one wyłączone. Treści wieloczęściowe (`<!--nextpage-->`) nie są obsługiwane.

Pierwsze trzy artykuły w pięciu językach tworzy `wp eval-file scripts/seed-blog.php` (okładki w `scripts/blog/covers`). Skrypt pomija istniejące artykuły, także te w koszu, więc nie nadpisuje zmian z panelu.

## Wyszukiwarka

Ikona lupy w nagłówku (oraz skróty `/` i Ctrl/⌘+K) otwiera okno wyszukiwania urządzeń, akcesoriów i artykułów w języku interfejsu. Przeglądarka pobiera raz mały indeks (`/wp-json/lasery/v1/search?lang=pl`, kilkanaście–kilkadziesiąt KB, przeglądarka przechowuje go 10 minut) i wyszukuje w nim od razu przy wpisywaniu, bez rozróżniania wielkości liter i polskich znaków; angielska nazwa ze sklepu xTool również znajduje produkt. Indeks jest odświeżany po zapisaniu produktu lub artykułu i po każdej synchronizacji cen. Bez JavaScriptu ten sam indeks obsługuje stronę wyników `/?s=…&lang=…`.

Strona główna pokazuje osiem pierwszych urządzeń (na telefonie i tablecie siedem, żeby siatka była pełna): najpierw maszyny z oznaczeniem demo, potem pozostałe, w obu grupach według pola **Kolejność**. Pozostałe są w katalogu `/produkty/`.

## Stan środowiska

- Domena `https://laseryxtool.pl` działa przez HTTPS na WordPressie. Katalog obejmuje urządzenia xTool oraz akcesoria.
- Istniejąca instalacja korzysta z bazy `host119363_wp874`. Dodatkowa baza `host119363_xtool` nie jest potrzebna do pracy z tą instalacją.
- SSH działa z lokalnym kluczem Hostido; na serwerze dostępne są PHP i WP-CLI.
- Odrębnego stagingu nie znaleziono w katalogach hostingu; może istnieć poza nimi i wymaga dalszego sprawdzenia.
- Domyślne treści WordPressa zostały przeniesione do szkiców; indeksowanie jest włączone.

## Założenia

- Prosta, czytelna strona w stylu landing page, z produktami, możliwością zakupu oraz kontaktem w sprawie demonstracji. Inspiracje: https://xtool.systems/en/ i https://www.xtool.eu/; strona główna powinna mieć mniej treści niż pierwsza z nich.
- Zakup ma odbywać się przez formularz zamówienia wysyłany do Michała. Michał ręcznie obsługuje zamówienie w xTool i następnie przekazuje klientowi link do płatności. Formularz ma zawierać produkt i cenę oraz przypominać pola faktycznego formularza xTool.
- Demo wykorzystuje stronę gospodarza xTool: `https://support.xtool.com/demo-room/host-instruction?id=1252&type=demoPreSale`, osadzoną w iframe i dostępną przez bezpośredni link.
- Opcjonalna ramka demo i zewnętrzne materiały w opisach ładują się po zgodzie; decyzję można zmienić w stopce. Wybrane zdjęcia produktów są zapisywane lokalnie jako WebP.
- Własny motyw i wtyczka do importowania oraz aktualizacji produktów. Źródłem jest katalog Shopify xTool; zakres danych wymaga doprecyzowania. Użytkownik potwierdził, że umowa zezwala na automatyczne pobieranie, publikowanie i tłumaczenie materiałów.
- Interfejs dostępny jest po polsku, angielsku, niemiecku, rosyjsku i ukraińsku. Nazwy i opisy produktów można edytować w każdym z tych języków; brakujący przekład wyświetla oryginał. Automatyczne tłumaczenie nowych produktów nie jest wdrożone.
- Osobny staging nie został jeszcze skonfigurowany.
- Cron na hostingu co godzinę odświeża ceny, warianty i dostępność wszystkich produktów jednym pobraniem katalogu xTool (pięć zapytań w odstępach dwóch sekund). Po odpowiedzi 429 pobieranie produktów i zdjęć jest wstrzymywane na godzinę (albo tyle, ile poda `Retry-After`, najwyżej dobę) i wznawia się samo.

Zakres katalogu oraz zasady aktualizowania ręcznych zmian wymagają dalszego doprecyzowania. Użytkownik potwierdził uprawnienia do używania materiałów xTool.

Nazwy, opisy i galerię produktów można edytować w panelu WordPressa. Wybór slajdów znajduje się w **Produkty xTool → Wyróżnione produkty**, a treści strony głównej i kontakt w **Wygląd → Personalizacja → Treść strony xTool**. Opisy polityki prywatności można edytować w ustawieniach wtyczki.

## Dalsza praca

1. Ustalić zakres produktów oraz sposób wiarygodnego prezentowania cen i wariantów.
2. Przygotować środowisko testowe i sposób jego odseparowania od produkcji.
3. Ustalić sposób lokalnego uruchamiania, wdrażania i testowania, a następnie udokumentować sprawdzone kroki w tym pliku.
4. Rozwijać motyw i wtyczkę w repozytorium oraz sprawdzać zmiany przed wdrożeniem na produkcję.

## Dane firmy i treści prawne

- MAJKEL MICHAŁ MAGALSKI, NIP 9532666193.
- ul. Stanisława Skarżyńskiego 5a, 85-791 Bydgoszcz, Polska.
- Kontakt: Michal@laseryxtool.pl.

W motywie przygotowano informację o prywatności w pięciu językach. Właściciel musi zapewnić usuwanie zgłoszeń niezakończonych zamówieniem po sześciu miesiącach od zakończenia ich obsługi. Regulamin i pozostałe informacje prawne wymagają przygotowania i dostosowania do rzeczywistego działania serwisu.

Hasła, klucze, pliki konfiguracyjne zawierające sekrety oraz zrzuty bazy nie należą do repozytorium. Dane dostępowe należy przekazywać i przechowywać bezpiecznie poza Git; nie wklejać ich do zgłoszeń ani czatu.
