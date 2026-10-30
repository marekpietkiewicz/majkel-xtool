# Lasery xTool — katalog

Pierwszy etap wtyczki WordPress: typ treści „Produkty xTool” i ręczny import szkiców. Typ treści ma publiczny widok i archiwum, ale importer tworzy wyłącznie szkice. Import i pobieranie wymagają uprawnień administratora (`manage_options` i `edit_posts`).

## Plik i import

Używaj wyłącznie danych, do których masz prawo. W panelu WordPressa wybierz **Produkty xTool → Import**, a następnie plik `.json` zawierający obiekt `products`, na przykład:

```json
{"products":[{"id":123456789,"title":"Przykładowa nazwa","body_html":"<p>Przykładowy opis.</p>"}]}
```

Format odpowiada strukturze odpowiedzi Shopify z tablicą `products`. Każdy produkt musi mieć dodatni liczbowy `id` (liczba całkowita lub jej zapis jako tekst) i niepusty tekstowy `title`; `body_html` może być tekstem, `null` lub go nie być. Powtórzony identyfikator w pliku powoduje odrzucenie całego pliku. Limit to 2 MiB i 500 produktów na import. Importer sprawdza całą strukturę przed zapisem i oczyszcza nazwę oraz dozwolony HTML opisu.

Nowe wpisy powstają jako szkice. Dla istniejącego identyfikatora, także w koszu, importer odświeża jedynie metadane źródłowe i nie nadpisuje ręcznych zmian treści. Wynik podaje liczby utworzonych i zaktualizowanych wpisów oraz błędów zapisu. Po błędach można ponowić import tego samego pliku.

Obie metody korzystają ze wspólnej blokady, aby nie uruchamiać importów równocześnie. Blokada jest odnawiana podczas zapisu i wygasa po 15 minutach bez odnowienia, na przykład po awarii procesu.

Przycisk **Pobierz do szkiców** pobiera dane ze stałego adresu `https://www.xtool.eu/products.json`, maksymalnie pięć stron po 100 produktów. Jeżeli limit zostanie osiągnięty, w źródle mogą pozostać dalsze produkty. Błąd sieci, nieprawidłowa odpowiedź lub powtarzające się identyfikatory przerywają pobieranie przed zapisem; wtyczka nie obchodzi blokad ani przekierowań. Limit odpowiedzi każdej strony wynosi 2 MiB. Wtyczka nie włącza harmonogramu samodzielnie.

Wtyczka udostępnia formularz zgłoszenia zamówienia na stronie opublikowanego produktu. Zawiera dane kontaktowe, adres dostawy, wariant, liczbę sztuk oraz opcjonalne dane do faktury i uwagi. Nie pobiera płatności. Właściciel otrzymuje e-mail z danymi i linkiem do checkoutu xTool, a klient — potwierdzenie. Link zawiera dane osobowe w adresie URL, zgodnie z wyborem właściciela; może on być zapisany w historii przeglądarki i logach. Treść formularza i wiadomości jest przygotowana w językach PL, EN, DE, RU i UK.

Importer zapisuje warianty, ich ceny, dostępność oraz adresy zdjęć jako osobne dane źródłowe. Walutę pobiera z metadanych sklepu; jeśli nie jest znana, ceny wymagają potwierdzenia. Formularz pozwala wybrać dostępny wariant i przedstawia cenę orientacyjną, gdy dane źródłowe są świeże. Ręcznie wpisana cena pozostaje dostępna dla produktów bez wariantów źródłowych.

Adres odbiorcy zamówień i nazwę nadawcy wiadomości (domyślnie „LaseryXTOOL.pl” zamiast „WordPress”) ustawia się w **Produkty xTool → Ustawienia zamówień**. Przed publikacją trzeba go ustawić; podczas testów należy użyć własnej skrzynki. Aktualne działanie `wp_mail` nie gwarantuje dostarczenia wiadomości.

Wybór kraju dostawy opiera się na kodach dostępnych w checkout xTool zaobserwowanych 30 października 2026 r. Lista może się zmienić i wymaga okresowej aktualizacji w `lasery_xtool_shipping_countries()`. Zamówienie z wariantem źródłowym zawiera w wiadomości link do checkoutu xTool z danymi klienta w adresie URL; może on trafić do historii przeglądarki i logów, dlatego należy traktować go jako poufny.

Do 20 zdjęć każdego produktu jest synchronizowanych partiami po trzy i zapisywanych jako WebP w bibliotece mediów. Najpierw pobierane są główne zdjęcia poszczególnych produktów, następnie pozostałe zdjęcia galerii. Galeria wyświetla tylko zapisane lokalnie obrazy. Multimedia osadzone wewnątrz opisów mogą nadal pochodzić ze źródeł zewnętrznych. Wtyczka nie tłumaczy treści produktów; osobne opisy w pięciu językach można uzupełnić w edycji produktu.

Synchronizacja hookiem `lasery_xtool_sync_existing` aktualizuje tylko dane źródłowe istniejących produktów; nie nadpisuje ręcznie edytowanych treści. W jednym przebiegu sprawdza maksymalnie jeden produkt o znanym identyfikatorze i poprawnym źródłowym `handle`. Pobiera dane JSON i JS tego produktu i zapisuje ofertę tylko wtedy, gdy identyfikatory wariantów oraz ceny w obu odpowiedziach są zgodne. Produkt bez poprawnego handle nie jest synchronizowany, a jego oferta nie jest uznawana za aktualną. Błędy pojedynczego produktu odkładają jego kolejną próbę o sześć godzin.

Osobny hook `lasery_xtool_sync_images` zapisuje obrazy, zachowując ręcznie wybrane miniatury. Po odpowiedzi HTTP 429 pobieranie produktów i obrazów zostaje wstrzymane i wymaga wznowienia w panelu importu. Ostatnia prawidłowa oferta pozostaje dostępna do końca swojego 24-godzinnego okresu ważności; po tym czasie formularz wstrzymuje wybór wariantu do aktualizacji. Systemowy harmonogram należy dobrać do wielkości katalogu i limitów źródła: jeden produkt na przebieg co sześć godzin oznacza najwyżej cztery produkty na dobę, co może nie wystarczyć do utrzymania aktualności większego katalogu.

W edycji produktu można wybrać własną, uporządkowaną galerię z biblioteki mediów albo przywrócić automatyczną. Wyboru maksymalnie czterech produktów do karuzeli dokonuje się w **Produkty xTool → Wyróżnione produkty**, a politykę prywatności w pięciu językach można edytować w **Produkty xTool → Polityka prywatności**.

Produkty mają adresy `/produkty/<slug>/`, a katalog — `/produkty/` (filtr `?kind=device` lub `?kind=accessory`). Po zmianie adresów trzeba odświeżyć reguły (`wp rewrite flush`). Kolejność produktów na stronie ustawia pole **Kolejność** w edycji produktu. Akcesoria oznacza się typem **Akcesoria** w taksonomii **Typy produktów** (kolumna i edycja zbiorcza na liście produktów); produkty bez tego typu są pokazywane jako urządzenia.

Formularz zamówienia przedstawia warianty jako listę do wyboru z cenami, a podsumowanie pokazuje wybrany wariant i cenę. Nazwy pól formularza i obsługa po stronie serwera są takie same jak wcześniej.

