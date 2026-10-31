<?php
/** Information about the order process and customer rights. */
if (!defined('ABSPATH')) {
    exit;
}

function lasery_xtool_purchase_info_default_texts($language) {
    $texts = [
        'pl' => [
            'title' => 'Informacje o zakupach',
            'operator_heading' => 'Kto prowadzi stronę i kto sprzedaje',
            'operator' => 'Serwis laseryxtool.pl prowadzi firma wskazana poniżej. Za jego pośrednictwem możesz przesłać nam zgłoszenie dotyczące produktów xTool. Sprzedawcą jest xTool. Przed zaakceptowaniem umowy sprawdź nazwę i dane sprzedawcy w ofercie lub dokumentach xTool; jeśli ich brakuje, skontaktuj się z nami lub z xTool.',
            'xtool_heading' => 'Dane kontaktowe publikowane przez xTool dla xtool.eu',
            'xtool_intro' => 'xTool wskazuje poniższy podmiot jako operatora xtool.eu i publikuje te dane kontaktowe. Dane sprzedawcy w Twojej transakcji sprawdź w ofercie i dokumentach przed zawarciem umowy.',
            'xtool_imprint' => 'Dane firmy xTool', 'xtool_contact_source' => 'Strona kontaktowa xTool',
            'process_heading' => 'Zgłoszenie i płatność',
            'process' => 'Ceny na tej stronie są orientacyjne. Po otrzymaniu zgłoszenia potwierdzimy dostępność, ostateczną cenę, VAT i koszt dostawy, a następnie prześlemy link do płatności xTool. Na tej stronie nie pobieramy płatności.',
            'rights_heading' => 'Reklamacje i zwroty',
            'rights' => 'Jeśli do umowy stosuje się polskie przepisy konsumenckie, konsument ma prawo reklamować u sprzedawcy towar niezgodny z umową. Jest to niezależne od gwarancji. Przy zakupie na odległość konsument ma co do zasady 14 dni od odbioru towaru na odstąpienie od umowy; przepisy przewidują wyjątki i sytuacje przedłużające termin. Przy osobnej dostawie wielu towarów, partii lub części termin biegnie od odbioru ostatniego towaru, partii lub części. Część tych praw przysługuje też osobie fizycznej prowadzącej działalność, gdy umowa jest z nią bezpośrednio związana, ale nie ma dla niej charakteru zawodowego. W innych transakcjach prawa określają właściwe przepisy. Ta informacja ani warunki handlowe nie ograniczają praw, których nie można wyłączyć na mocy prawa.',
            'contact' => 'W sprawie zakupu możesz napisać do nas:',
            'seller_contact' => 'Reklamację dotyczącą umowy z xTool lub oświadczenie o odstąpieniu możesz skierować do sprzedawcy. Dane kontaktowe znajdziesz w dokumentach transakcji oraz centrum pomocy xTool.',
            'links_heading' => 'Dokumenty i kontakt xTool', 'terms' => 'Warunki korzystania xTool', 'returns' => 'Polityka zwrotów xTool', 'support' => 'Centrum pomocy xTool',
        ],
        'en' => [
            'title' => 'Purchase information',
            'operator_heading' => 'Site operator and seller',
            'operator' => 'laseryxtool.pl is operated by the business identified below. You can use it to send us an enquiry about xTool products. xTool is the seller. Before accepting a contract, check the seller’s legal name and details in xTool’s offer or documents; if they are missing, contact us or xTool.',
            'xtool_heading' => 'Contact details published by xTool for xtool.eu',
            'xtool_intro' => 'xTool identifies the company below as the operator of xtool.eu and publishes these contact details. Before entering into a contract, check the seller’s details in your offer and transaction documents.',
            'xtool_imprint' => 'xTool company details', 'xtool_contact_source' => 'xTool contact page',
            'process_heading' => 'Enquiry and payment',
            'process' => 'Prices on this site are estimates. After receiving your enquiry, we will confirm availability, the final price, VAT and delivery cost, then send an xTool payment link. We do not collect payment on this site.',
            'rights_heading' => 'Complaints and returns',
            'rights' => 'Where Polish consumer law applies, a consumer has the right to complain to the seller about goods that do not conform to the contract. This right is separate from any warranty. For a distance purchase, a consumer generally has 14 days from receiving the goods to withdraw; statutory exceptions and extensions apply. When multiple goods, lots or parts are delivered separately, the period starts on receipt of the last one. Some of these rights also protect an individual carrying on a business when the contract is directly related to that business but is not professional in nature for them. For other transactions, rights are determined by the applicable law. Nothing in this information or in commercial terms limits rights that cannot be excluded by law.',
            'contact' => 'You can contact us with questions about your purchase:',
            'seller_contact' => 'You can send a complaint or notice of withdrawal concerning your contract with xTool to the seller. Contact details are in your transaction documents and the xTool support centre.',
            'links_heading' => 'xTool documents and contact', 'terms' => 'xTool terms of service', 'returns' => 'xTool return policy', 'support' => 'xTool support centre',
        ],
        'de' => [
            'title' => 'Informationen zum Kauf',
            'operator_heading' => 'Websitebetreiber und Verkäufer',
            'operator' => 'laseryxtool.pl wird von dem unten genannten Unternehmen betrieben. Über die Website können Sie uns eine Anfrage zu xTool-Produkten senden. Verkäufer ist xTool. Prüfen Sie vor Annahme eines Vertrags Namen und Daten des Verkäufers im Angebot oder in den Unterlagen von xTool; fehlen diese Angaben, kontaktieren Sie uns oder xTool.',
            'xtool_heading' => 'Von xTool veröffentlichte Kontaktdaten für xtool.eu',
            'xtool_intro' => 'xTool nennt das folgende Unternehmen als Betreiber von xtool.eu und veröffentlicht diese Kontaktdaten. Prüfen Sie vor Vertragsabschluss die Daten des Verkäufers in Ihrem Angebot und Ihren Transaktionsunterlagen.',
            'xtool_imprint' => 'Unternehmensdaten von xTool', 'xtool_contact_source' => 'Kontaktseite von xTool',
            'process_heading' => 'Anfrage und Zahlung',
            'process' => 'Die Preise auf dieser Website dienen zur Orientierung. Nach Ihrer Anfrage bestätigen wir Verfügbarkeit, endgültigen Preis, Mehrwertsteuer und Versandkosten und senden Ihnen anschließend einen Zahlungslink von xTool. Auf dieser Website nehmen wir keine Zahlung entgegen.',
            'rights_heading' => 'Reklamation und Rückgabe',
            'rights' => 'Gilt für den Vertrag polnisches Verbraucherrecht, hat der Verbraucher das Recht, eine nicht vertragsgemäße Ware beim Verkäufer zu reklamieren. Dieses Recht besteht unabhängig von einer Garantie. Bei einem Fernabsatzkauf hat der Verbraucher grundsätzlich 14 Tage ab Erhalt der Ware für den Widerruf; gesetzliche Ausnahmen und Fristverlängerungen sind möglich. Werden mehrere Waren, Teilsendungen oder Teile getrennt geliefert, beginnt die Frist mit dem Erhalt der letzten Lieferung. Einige dieser Rechte gelten auch für eine natürliche Person mit Geschäftstätigkeit, wenn der Vertrag unmittelbar damit zusammenhängt, für sie aber keinen beruflichen Charakter hat. Für andere Geschäfte bestimmt das anwendbare Recht die Rechte. Diese Informationen und die Geschäftsbedingungen schränken gesetzlich unabdingbare Rechte nicht ein.',
            'contact' => 'Bei Fragen zum Kauf können Sie uns kontaktieren:',
            'seller_contact' => 'Eine Reklamation oder einen Widerruf zu Ihrem Vertrag mit xTool können Sie an den Verkäufer richten. Kontaktdaten finden Sie in Ihren Transaktionsunterlagen und im xTool-Hilfezentrum.',
            'links_heading' => 'Dokumente und Kontakt von xTool', 'terms' => 'Nutzungsbedingungen von xTool', 'returns' => 'Rückgaberichtlinie von xTool', 'support' => 'xTool-Hilfezentrum',
        ],
        'ru' => [
            'title' => 'Информация о покупке',
            'operator_heading' => 'Владелец сайта и продавец',
            'operator' => 'Сайт laseryxtool.pl ведёт указанная ниже компания. Через сайт вы можете отправить нам запрос о продукции xTool. Продавцом является xTool. До заключения договора проверьте название и данные продавца в предложении или документах xTool; если их нет, свяжитесь с нами или xTool.',
            'xtool_heading' => 'Контактные данные xTool для xtool.eu',
            'xtool_intro' => 'xTool указывает приведённую ниже компанию как оператора xtool.eu и публикует эти контактные данные. До заключения договора проверьте данные продавца в вашем предложении и документах сделки.',
            'xtool_imprint' => 'Реквизиты компании xTool', 'xtool_contact_source' => 'Страница контактов xTool',
            'process_heading' => 'Запрос и оплата',
            'process' => 'Цены на сайте ориентировочные. После получения запроса мы подтвердим наличие, окончательную цену, НДС и стоимость доставки, а затем отправим ссылку на оплату xTool. На этом сайте мы не принимаем оплату.',
            'rights_heading' => 'Претензии и возврат',
            'rights' => 'Если к договору применяется польское законодательство о защите потребителей, потребитель вправе предъявить продавцу претензию по поводу несоответствия товара договору. Это право действует независимо от гарантии. При дистанционной покупке потребитель, как правило, может отказаться от договора в течение 14 дней после получения товара; закон предусматривает исключения и продление срока. При раздельной доставке нескольких товаров, партий или частей срок начинается с получения последней из них. Некоторые из этих прав также предоставляются физическому лицу, ведущему бизнес, если договор непосредственно связан с его деятельностью, но не носит для него профессионального характера. Для других сделок права определяются применимым законодательством. Эта информация и коммерческие условия не ограничивают права, которые нельзя исключить по закону.',
            'contact' => 'По вопросам покупки вы можете написать нам:',
            'seller_contact' => 'Претензию или заявление об отказе от договора с xTool можно направить продавцу. Контактные данные указаны в документах сделки и в центре поддержки xTool.',
            'links_heading' => 'Документы и контакты xTool', 'terms' => 'Условия использования xTool', 'returns' => 'Политика возврата xTool', 'support' => 'Центр поддержки xTool',
        ],
        'uk' => [
            'title' => 'Інформація про покупку',
            'operator_heading' => 'Власник сайту та продавець',
            'operator' => 'Сайт laseryxtool.pl веде зазначена нижче компанія. Через сайт ви можете надіслати нам запит щодо продукції xTool. Продавцем є xTool. До укладення договору перевірте назву й дані продавця в пропозиції або документах xTool; якщо їх немає, зв’яжіться з нами або xTool.',
            'xtool_heading' => 'Контактні дані xTool для xtool.eu',
            'xtool_intro' => 'xTool зазначає наведену нижче компанію як оператора xtool.eu та публікує ці контактні дані. До укладення договору перевірте дані продавця у вашій пропозиції та документах угоди.',
            'xtool_imprint' => 'Реквізити компанії xTool', 'xtool_contact_source' => 'Сторінка контактів xTool',
            'process_heading' => 'Запит і оплата',
            'process' => 'Ціни на сайті орієнтовні. Після отримання запиту ми підтвердимо наявність, остаточну ціну, ПДВ і вартість доставки, а потім надішлемо посилання на оплату xTool. На цьому сайті ми не приймаємо оплату.',
            'rights_heading' => 'Рекламації та повернення',
            'rights' => 'Якщо до договору застосовується польське законодавство про захист споживачів, споживач має право подати продавцю рекламацію щодо невідповідності товару договору. Це право діє незалежно від гарантії. У разі дистанційної покупки споживач зазвичай має 14 днів після отримання товару для відмови від договору; закон передбачає винятки та продовження строку. Якщо кілька товарів, партій або частин доставляють окремо, строк починається з отримання останнього з них. Деякі з цих прав також належать фізичній особі, яка веде бізнес, якщо договір безпосередньо пов’язаний з її діяльністю, але не має для неї професійного характеру. Для інших угод права визначаються застосовним законодавством. Ця інформація та комерційні умови не обмежують прав, які не можна виключити за законом.',
            'contact' => 'Із запитаннями щодо покупки ви можете написати нам:',
            'seller_contact' => 'Рекламацію або заяву про відмову від договору з xTool можна надіслати продавцю. Контактні дані наведено в документах угоди та центрі підтримки xTool.',
            'links_heading' => 'Документи та контакти xTool', 'terms' => 'Умови використання xTool', 'returns' => 'Політика повернення xTool', 'support' => 'Центр підтримки xTool',
        ],
    ];
    $contact = [
        'xtool_entity' => 'Makeblock Europe B.V.',
        'xtool_address' => 'Oostergracht 48, 3763 LZ Soest, Netherlands',
        'xtool_email' => 'service.eu@xtool.com',
        'xtool_phone' => '+49 211 860 89128',
    ];
    return (isset($texts[$language]) ? $texts[$language] : $texts['pl']) + $contact;
}

/** Administrators can edit the document in each language under Teksty strony. */
function lasery_xtool_purchase_info_texts($language) {
    $texts = lasery_xtool_purchase_info_default_texts($language);
    return function_exists('lasery_xtool_apply_text_overrides') ? lasery_xtool_apply_text_overrides('purchase_info', $language, $texts) : $texts;
}

function lasery_xtool_purchase_info_title($language = '') {
    $language = $language !== '' ? $language : lasery_xtool_interface_language();
    return lasery_xtool_purchase_info_texts($language)['title'];
}

/** Link only to the published document; the plugin creates it during the update. */
function lasery_xtool_purchase_info_url($language = '') {
    $page = get_page_by_path('informacje-o-zakupach');
    if (!$page || $page->post_status !== 'publish' || $page->post_password !== '') {
        return '';
    }
    $language = $language !== '' ? $language : lasery_xtool_interface_language();
    return add_query_arg('lang', $language, get_permalink($page));
}
