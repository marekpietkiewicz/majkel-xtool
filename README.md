# laseryxtool.pl

Projekt witryny o produktach xTool na WordPressie i hostingu Hostido. Motyw prezentuje katalog, rezerwację demonstracji i formularz zgłoszenia zamówienia. Wtyczka importuje dane produktów z xTool.

Kod znajduje się w `wp-content/themes/lasery-xtool` i `wp-content/plugins/lasery-xtool`. [Instrukcja importu](wp-content/plugins/lasery-xtool/README.md) opisuje aktualny zakres i ograniczenia wtyczki.

Zdjęcie w pierwszej sekcji pochodzi z materiałów xTool: [źródło w CDN Shopify](https://cdn.shopify.com/s/files/1/0467/7985/9095/files/mk-c1_eu_pc_pc-x1_3180-1913.webp?v=1792481580).

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
- Cron na hostingu uruchamia synchronizację źródłowych danych istniejących produktów w małych partiach. Po 429 pobieranie jest wstrzymywane do ręcznego wznowienia.

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
