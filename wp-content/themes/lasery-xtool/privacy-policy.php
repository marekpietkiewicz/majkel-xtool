<?php
/** Privacy information for the WordPress privacy-policy page. */
get_header();
$language = function_exists('pll_current_language') ? pll_current_language('slug') : '';
if (!is_string($language) || $language === '') {
    $language = substr(get_locale(), 0, 2);
}
$privacy = [
    'pl' => [
        'title' => 'Polityka prywatności',
        'controller' => 'Administrator',
        'controller_text' => 'Administratorem danych jest MAJKEL MICHAŁ MAGALSKI, NIP 9532666193, ul. Stanisława Skarżyńskiego 5a, 85-791 Bydgoszcz. Kontakt w sprawach danych osobowych: Michal@laseryxtool.pl.',
        'purposes' => 'Jak wykorzystujemy dane',
        'purposes_text' => 'Dane podane w formularzu zamówienia wykorzystujemy do kontaktu, przygotowania i obsługi zamówienia oraz przekazania szczegółów płatności. Podstawą jest podjęcie działań na Twoje żądanie przed zawarciem umowy lub jej wykonanie (art. 6 ust. 1 lit. b RODO). Podanie danych oznaczonych jako wymagane jest potrzebne do obsługi zgłoszenia; pozostałe są dobrowolne.',
        'storage' => 'Jak długo przechowujemy dane',
        'storage_text' => 'Dane ze zgłoszeń, które nie doprowadziły do zamówienia, przechowujemy przez 6 miesięcy od zakończenia ich obsługi. Dane dotyczące zrealizowanych zamówień przechowujemy przez okres wynikający z obowiązków prawnych i terminów dochodzenia roszczeń.',
        'recipients' => 'Odbiorcy i demo xTool',
        'recipients_text' => 'Dostęp do danych mogą mieć dostawcy hostingu i poczty oraz xTool, gdy przekazujemy mu dane potrzebne do realizacji zamówienia. Formularz demo jest udostępniany przez xTool w osadzonej ramce. Po załadowaniu ramki xTool może otrzymać informacje o połączeniu, a po użyciu formularza — dane przez Ciebie wpisane. Zasady xTool opisuje jego polityka prywatności.',
        'rights' => 'Twoje prawa',
        'rights_text' => 'Możesz zwrócić się do nas o dostęp do danych, ich sprostowanie lub usunięcie, ograniczenie przetwarzania oraz przeniesienie danych, a także wnieść sprzeciw, gdy ma zastosowanie. Masz prawo złożyć skargę do Prezesa Urzędu Ochrony Danych Osobowych. W sprawie praw skontaktuj się z nami pod adresem powyżej.',
        'external' => 'Polityka prywatności xTool',
    ],
    'en' => [
        'title' => 'Privacy policy', 'controller' => 'Controller',
        'controller_text' => 'The controller is MAJKEL MICHAŁ MAGALSKI, tax ID 9532666193, ul. Stanisława Skarżyńskiego 5a, 85-791 Bydgoszcz, Poland. Contact for personal data matters: Michal@laseryxtool.pl.',
        'purposes' => 'How we use your data', 'purposes_text' => 'We use information submitted through the order form to contact you, prepare and handle your order, and provide payment details. The legal basis is taking steps at your request before entering into a contract or performing a contract (Article 6(1)(b) GDPR). Required information is needed to handle your request; other information is optional.',
        'storage' => 'How long we keep your data', 'storage_text' => 'We keep data from requests that do not result in an order for six months after handling is complete. Data relating to completed orders is kept for the periods required by law and applicable claim limitation periods.',
        'recipients' => 'Recipients and xTool demos', 'recipients_text' => 'Hosting and email providers may have access to your data, as may xTool when we pass on information needed to fulfil an order. The demo form is provided by xTool in an embedded frame. When it loads, xTool may receive connection information and, if you use the form, the data you enter. See xTool’s privacy policy for its practices.',
        'rights' => 'Your rights', 'rights_text' => 'You may request access, correction, erasure, restriction or portability of your data and object where applicable. You may lodge a complaint with the Polish Personal Data Protection Office (UODO). Contact us at the address above to exercise your rights.', 'external' => 'xTool privacy policy',
    ],
    'de' => [
        'title' => 'Datenschutzerklärung', 'controller' => 'Verantwortlicher',
        'controller_text' => 'Verantwortlich ist MAJKEL MICHAŁ MAGALSKI, Steuer-ID 9532666193, ul. Stanisława Skarżyńskiego 5a, 85-791 Bydgoszcz, Polen. Kontakt zum Datenschutz: Michal@laseryxtool.pl.',
        'purposes' => 'Verwendung Ihrer Daten', 'purposes_text' => 'Wir verwenden die im Bestellformular angegebenen Daten, um Sie zu kontaktieren, Ihre Bestellung vorzubereiten und zu bearbeiten und Zahlungsinformationen bereitzustellen. Rechtsgrundlage ist die Durchführung vorvertraglicher Maßnahmen auf Ihre Anfrage oder die Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO). Die als erforderlich gekennzeichneten Angaben benötigen wir für die Bearbeitung; weitere Angaben sind freiwillig.',
        'storage' => 'Speicherdauer', 'storage_text' => 'Daten aus Anfragen, die nicht zu einer Bestellung führen, speichern wir sechs Monate nach Abschluss der Bearbeitung. Daten abgeschlossener Bestellungen speichern wir für die gesetzlich vorgeschriebenen Zeiträume und geltenden Verjährungsfristen.',
        'recipients' => 'Empfänger und xTool-Demo', 'recipients_text' => 'Hosting- und E-Mail-Anbieter können Zugang zu Daten erhalten, ebenso xTool, wenn wir die zur Bestellabwicklung erforderlichen Angaben weitergeben. Das Demoformular wird von xTool in einem eingebetteten Rahmen bereitgestellt. Beim Laden kann xTool Verbindungsdaten erhalten, bei Nutzung des Formulars auch Ihre Eingaben. Die Datenschutzbestimmungen von xTool erläutern dessen Verarbeitung.',
        'rights' => 'Ihre Rechte', 'rights_text' => 'Sie können Auskunft, Berichtigung, Löschung, Einschränkung oder Übertragbarkeit Ihrer Daten verlangen und gegebenenfalls Widerspruch einlegen. Sie können sich bei der polnischen Datenschutzbehörde UODO beschweren. Wenden Sie sich zur Ausübung Ihrer Rechte an die oben genannte Adresse.', 'external' => 'Datenschutzerklärung von xTool',
    ],
    'ru' => [
        'title' => 'Политика конфиденциальности', 'controller' => 'Оператор данных',
        'controller_text' => 'Оператор данных — MAJKEL MICHAŁ MAGALSKI, налоговый номер 9532666193, ul. Stanisława Skarżyńskiego 5a, 85-791 Быдгощ, Польша. По вопросам персональных данных: Michal@laseryxtool.pl.',
        'purposes' => 'Как мы используем данные', 'purposes_text' => 'Данные из формы заказа используются для связи с вами, подготовки и обработки заказа и передачи сведений об оплате. Основание — действия по вашему запросу до заключения договора или исполнение договора (статья 6(1)(b) GDPR). Обязательные данные необходимы для обработки заявки; остальные предоставляются добровольно.',
        'storage' => 'Срок хранения', 'storage_text' => 'Данные заявок, не приведших к заказу, хранятся шесть месяцев после завершения их обработки. Данные выполненных заказов хранятся в течение сроков, установленных законом и применимых к требованиям.',
        'recipients' => 'Получатели и демонстрация xTool', 'recipients_text' => 'Доступ к данным могут получать поставщики хостинга и электронной почты, а также xTool, когда мы передаём ему сведения, необходимые для выполнения заказа. Форма демонстрации предоставляется xTool во встроенной рамке. При её загрузке xTool может получить сведения о соединении, а при использовании формы — введённые вами данные. Порядок обработки описан в политике xTool.',
        'rights' => 'Ваши права', 'rights_text' => 'Вы можете запросить доступ к данным, их исправление, удаление, ограничение обработки или перенос, а также возразить против обработки, когда это применимо. Вы можете подать жалобу в польский орган защиты персональных данных UODO. Для реализации прав свяжитесь с нами по указанному адресу.', 'external' => 'Политика конфиденциальности xTool',
    ],
    'uk' => [
        'title' => 'Політика конфіденційності', 'controller' => 'Адміністратор даних',
        'controller_text' => 'Адміністратор даних — MAJKEL MICHAŁ MAGALSKI, податковий номер 9532666193, ul. Stanisława Skarżyńskiego 5a, 85-791 Бидгощ, Польща. З питань персональних даних: Michal@laseryxtool.pl.',
        'purposes' => 'Як ми використовуємо дані', 'purposes_text' => 'Дані з форми замовлення ми використовуємо для зв’язку з вами, підготовки й обробки замовлення та надання платіжної інформації. Підстава — дії на ваш запит до укладення договору або виконання договору (стаття 6(1)(b) GDPR). Обов’язкові дані потрібні для обробки звернення; інші надаються добровільно.',
        'storage' => 'Строк зберігання', 'storage_text' => 'Дані звернень, які не привели до замовлення, зберігаються шість місяців після завершення їх обробки. Дані виконаних замовлень зберігаються протягом строків, установлених законом і застосовних до вимог.',
        'recipients' => 'Отримувачі та демонстрація xTool', 'recipients_text' => 'Доступ до даних можуть отримувати постачальники хостингу й електронної пошти, а також xTool, коли ми передаємо йому дані, потрібні для виконання замовлення. Форму демонстрації надає xTool у вбудованому фреймі. Під час його завантаження xTool може отримати дані про з’єднання, а під час використання форми — введені вами дані. Порядок обробки описаний у політиці xTool.',
        'rights' => 'Ваші права', 'rights_text' => 'Ви можете запросити доступ до даних, їх виправлення, видалення, обмеження обробки або перенесення, а також заперечити проти обробки, коли це застосовно. Ви можете подати скаргу до польського органу захисту персональних даних UODO. Для реалізації прав зв’яжіться з нами за адресою вище.', 'external' => 'Політика конфіденційності xTool',
    ],
];
$text = isset($privacy[$language]) ? $privacy[$language] : $privacy['pl'];
?>
<article class="article privacy-policy">
    <h1><?php echo esc_html($text['title']); ?></h1>
    <?php foreach (['controller', 'purposes', 'storage', 'recipients', 'rights'] as $section) : ?>
        <section>
            <h2><?php echo esc_html($text[$section]); ?></h2>
            <p><?php echo esc_html($text[$section . '_text']); ?></p>
        </section>
    <?php endforeach; ?>
    <p><a href="https://www.xtool.eu/policies/privacy-policy" rel="noopener noreferrer"><?php echo esc_html($text['external']); ?></a></p>
</article>
<?php get_footer(); ?>
