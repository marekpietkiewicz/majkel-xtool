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

Przycisk **Pobierz do szkiców** pobiera dane ze stałego adresu `https://www.xtool.eu/products.json`, maksymalnie pięć stron po 100 produktów. Jeżeli limit zostanie osiągnięty, w źródle mogą pozostać dalsze produkty. Błąd sieci, nieprawidłowa odpowiedź lub powtarzające się identyfikatory przerywają pobieranie przed zapisem; wtyczka nie obchodzi blokad ani przekierowań. Limit odpowiedzi każdej strony wynosi 2 MiB. Automatyczny import okresowy nie jest skonfigurowany.

Wtyczka udostępnia formularz zgłoszenia zamówienia na stronie opublikowanego produktu. Zawiera dane kontaktowe, adres dostawy, wariant, liczbę sztuk oraz opcjonalne dane do faktury i uwagi. Nie pobiera płatności. Właściciel otrzymuje e-mail z danymi i linkiem do checkoutu xTool, a klient — potwierdzenie. Link zawiera dane osobowe w adresie URL, zgodnie z wyborem właściciela; może on być zapisany w historii przeglądarki i logach. Treść formularza i wiadomości jest przygotowana w językach PL, EN, DE, RU i UK.

Importer zapisuje warianty, ich ceny, dostępność oraz adres głównego zdjęcia jako osobne dane źródłowe. Walutę pobiera z metadanych sklepu; jeśli nie jest znana, ceny wymagają potwierdzenia. Formularz pozwala wybrać dostępny wariant i przedstawia cenę orientacyjną, gdy dane źródłowe są świeże. Ręcznie wpisana cena pozostaje dostępna dla produktów bez wariantów źródłowych.

Adres odbiorcy zamówień ustawia się w **Produkty xTool → Ustawienia zamówień**. Przed publikacją trzeba go ustawić; podczas testów należy użyć własnej skrzynki. Aktualne działanie `wp_mail` nie gwarantuje dostarczenia wiadomości.

Wybór kraju dostawy opiera się na kodach dostępnych w checkout xTool zaobserwowanych 30 października 2026 r. Lista może się zmienić i wymaga okresowej aktualizacji w `lasery_xtool_shipping_countries()`. Zamówienie z wariantem źródłowym zawiera w wiadomości link do checkoutu xTool z danymi klienta w adresie URL; może on trafić do historii przeglądarki i logów, dlatego należy traktować go jako poufny.

Do 20 zdjęć każdego produktu jest synchronizowanych partiami po trzy i zapisywanych jako WebP w bibliotece mediów. Do czasu pobrania galeria może korzystać ze źródłowego CDN. Multimedia osadzone wewnątrz opisów mogą nadal pochodzić ze źródeł zewnętrznych. Wtyczka nie tłumaczy treści produktów.

Synchronizacja hookiem `lasery_xtool_sync_existing` aktualizuje tylko dane źródłowe istniejących produktów; nie nadpisuje ręcznie edytowanych treści. Osobny hook `lasery_xtool_sync_images` zapisuje obrazy, zachowując ręcznie wybrane miniatury. Po odpowiedzi HTTP 429 pobieranie zostaje wstrzymane i wymaga wznowienia w panelu importu. Oferty starsze niż 24 godziny nie są przyjmowane przez formularz do czasu aktualizacji.
