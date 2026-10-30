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

Wtyczka udostępnia formularz zgłoszenia zamówienia na stronie opublikowanego produktu. Zawiera dane kontaktowe, adres dostawy, wariant opisany przez klienta, liczbę sztuk oraz opcjonalne dane do faktury i uwagi. Nie pobiera płatności. Po wysłaniu danych właściciel otrzymuje wiadomość, a klient — potwierdzenie; dostarczalność wymaga sprawdzenia w docelowym środowisku. Treść formularza i wiadomości jest przygotowana w językach PL, EN, DE, RU i UK; wybór języka zależy od konfiguracji WordPressa lub Polylang.

Importer zapisuje warianty, ich ceny, dostępność oraz adres głównego zdjęcia jako osobne dane źródłowe. Walutę pobiera z metadanych sklepu; jeśli nie jest znana, ceny wymagają potwierdzenia. Formularz pozwala wybrać dostępny wariant i przedstawia cenę orientacyjną, gdy dane źródłowe są świeże. Ręcznie wpisana cena pozostaje dostępna dla produktów bez wariantów źródłowych.

Adres odbiorcy zamówień ustawia się w **Produkty xTool → Ustawienia zamówień**. Domyślny adres do testów to `mmpietkiewicz@gmail.com`; przed publikacją trzeba zmienić go na właściwą skrzynkę. Aktualne działanie `wp_mail` nie gwarantuje dostarczenia wiadomości.

Zdjęcie jest wyświetlane ze źródłowego CDN, nie jest pobierane do biblioteki mediów. Opisy są zapisywane w języku oryginału. Wtyczka nie tłumaczy treści produktów.

Synchronizacja hookiem `lasery_xtool_sync_existing` aktualizuje tylko dane źródłowe istniejących produktów; nie nadpisuje ręcznie edytowanych treści. Po odpowiedzi HTTP 429 pobieranie zostaje wstrzymane i wymaga wznowienia w panelu importu. Oferty starsze niż 24 godziny nie są przyjmowane przez formularz do czasu aktualizacji.
