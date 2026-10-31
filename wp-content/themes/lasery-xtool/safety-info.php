<?php
/** Public safety information. Product-specific notices are independent of editable descriptions. */
if (!defined('ABSPATH')) {
    exit;
}

function lasery_xtool_safety_url() {
    $page = get_page_by_path('bezpieczenstwo');
    return $page && $page->post_status === 'publish' && $page->post_password === '' ? lasery_xtool_language_url(get_permalink($page)) : '';
}

function lasery_xtool_safety_texts($language = '') {
    $language = $language !== '' ? $language : lasery_xtool_interface_language();
    $texts = [
        'pl' => [
            'title' => 'Bezpieczeństwo pracy z laserem',
            'intro' => 'Lasery mogą spowodować trwałe uszkodzenie oczu i skóry, pożar oraz powstanie szkodliwych oparów. Przed zakupem i użyciem sprawdź instrukcję dokładnej wersji urządzenia i wszystkich używanych akcesoriów.',
            'general_heading' => 'Przygotowanie stanowiska',
            'general' => 'Dobierz zabezpieczenia stanowiska, ochronę oczu i odciąg do urządzenia, materiału i sposobu pracy. Nie patrz bezpośrednio w wiązkę ani jej odbicia, nawet w okularach. Nie uruchamiaj urządzenia z uszkodzoną osłoną lub niesprawną blokadą; nie obchodź zabezpieczeń. Pracującego lasera nie zostawiaj bez nadzoru.',
            'materials_heading' => 'Materiały i opary',
            'materials' => 'Nie tnij ani nie graweruj laserem PVC, PTFE ani materiałów o nieznanym składzie. Sprawdź zalecenia producenta materiału i urządzenia. Zapewnij odciąg dobrany do obrabianego materiału i zgodny z miejscowymi przepisami; sam filtr nie usuwa każdego zagrożenia.',
            'f2_heading' => 'xTool F2',
            'f2_package' => 'Według FAQ xTool okulary nie są dołączone do samego urządzenia. Sprawdź zawartość wybranego zestawu.',
            'f2' => 'F2 jest laserem klasy 4 do użytku profesjonalnego. xTool wymaga przeszkolenia, nadzoru osoby odpowiedzialnej za bezpieczeństwo laserowe oraz okularów chroniących przed promieniowaniem 445 ± 15 nm, 655 ± 5 nm i 1064 ± 5 nm. Okulary są wymagane także przy zamkniętej osłonie. Nie używaj F2 w miejscach publicznych lub mieszkalnych ani przy dzieciach lub osobach nieprzeszkolonych.',
            'ultra_heading' => 'xTool F2 Ultra',
            'ultra' => 'Wymagania zależą od wersji urządzenia. Instrukcja modeli MXF-K003-003 i MXF-K004-003 wymaga ochrony oczu przed promieniowaniem 445 ± 15 nm i 1064 ± 5 nm. Tabela doboru okularów xTool uwzględnia też wskaźnik 655 ± 5 nm. Dobierz okulary i pozostałe zabezpieczenia według instrukcji konkretnego modelu i oceny zagrożeń.',
            'uv_heading' => 'xTool F2 Ultra UV i RA3',
            'uv' => 'F2 Ultra UV występuje w wersjach o różnych klasach bezpieczeństwa. Sprawdź numer modelu oraz jego instrukcję i dobierz ochronę do używanych źródeł. Według xTool F2 i F2 Ultra UV z przystawką RA3 nie pracują w warunkach klasy 1.',
            'p3_heading' => 'xTool P3 z automatycznym podajnikiem taśmowym',
            'p3' => 'P3 z automatycznym podajnikiem taśmowym jest laserem klasy 4. Producent wymaga przeszkolenia, nadzoru osoby odpowiedzialnej za bezpieczeństwo laserowe, zabezpieczonego stanowiska oraz okularów dobranych do używanych laserów, w tym do fali 10,6 µm. Nie używaj tej konfiguracji w miejscach publicznych ani przy dzieciach lub osobach nieprzeszkolonych.',
            'sources' => 'Instrukcje i informacje producenta',
            'notice_heading' => 'Ważne informacje o bezpieczeństwie',
            'notice_f2' => 'F2 to laser klasy 4. xTool wymaga okularów ochronnych także przy zamkniętej osłonie oraz przeszkolenia i nadzoru osoby odpowiedzialnej za bezpieczeństwo laserowe. Nie używaj go w miejscach publicznych ani mieszkalnych, przy dzieciach lub osobach nieprzeszkolonych.',
            'notice_ultra' => 'F2 Ultra występuje w wersjach klasy 4 do użytku profesjonalnego. Sprawdź model: xTool wymaga przeszkolenia, nadzoru osoby odpowiedzialnej za bezpieczeństwo laserowe oraz ochrony oczu dobranej zgodnie z instrukcją.',
            'notice_uv' => 'F2 Ultra UV występuje w wersjach o różnych klasach bezpieczeństwa. Sprawdź model, instrukcję i wymaganą ochronę; z RA3 nie pracuje w warunkach klasy 1.',
            'notice_p3' => 'P3 z automatycznym podajnikiem taśmowym jest laserem klasy 4 i wymaga dodatkowych zabezpieczeń, przeszkolenia oraz właściwie dobranych okularów.',
            'notice_ra3' => 'Według xTool F2 i F2 Ultra UV z przystawką RA3 nie pracują w warunkach klasy 1. Sprawdź instrukcję używanego urządzenia i wymagane zabezpieczenia.',
            'notice_duo' => 'Zestaw obejmuje różne modele laserów. Sprawdź instrukcję i środki ochrony wymagane dla każdego urządzenia; wersje F2 Ultra UV mają różne klasy bezpieczeństwa.',
            'more' => 'Przeczytaj informacje o bezpieczeństwie',
        ],
        'en' => [
            'title' => 'Laser safety',
            'intro' => 'Lasers can cause permanent eye and skin injuries, fire and harmful fumes. Before buying or using a machine, read the instructions for its exact version and every accessory you use.',
            'general_heading' => 'Preparing the work area',
            'general' => 'Choose safeguards, eye protection and extraction for the machine, material and operation. Never look directly into the beam or its reflections, even with goggles. Do not operate a machine with a damaged enclosure or faulty interlock, and do not bypass safeguards. Never leave a running laser unattended.',
            'materials_heading' => 'Materials and fumes',
            'materials' => 'Do not laser-cut or engrave PVC, PTFE or materials of unknown composition. Check the material and machine manufacturers’ instructions. Provide extraction suited to the material and local regulations; a filter alone does not eliminate every hazard.',
            'f2_heading' => 'xTool F2',
            'f2_package' => 'According to xTool’s FAQ, goggles are not included with the machine itself. Check the contents of your chosen bundle.',
            'f2' => 'The F2 is a Class 4 laser for professional use. xTool requires training, oversight by a laser safety officer and goggles protecting against 445 ± 15 nm, 655 ± 5 nm and 1064 ± 5 nm radiation. Goggles are required even when the enclosure is closed. Do not use the F2 in public or residential areas, or around children or untrained people.',
            'ultra_heading' => 'xTool F2 Ultra',
            'ultra' => 'Requirements depend on the machine version. The instructions for models MXF-K003-003 and MXF-K004-003 require eye protection against 445 ± 15 nm and 1064 ± 5 nm radiation. xTool’s goggles selection table also includes the 655 ± 5 nm indicator. Choose goggles and other safeguards according to the instructions for your exact model and the risk assessment.',
            'uv_heading' => 'xTool F2 Ultra UV and RA3',
            'uv' => 'The F2 Ultra UV is available in versions with different safety classes. Check the model number and its instructions, and select protection for the sources in use. According to xTool, the F2 and F2 Ultra UV do not operate under Class 1 conditions when used with the RA3.',
            'p3_heading' => 'xTool P3 with the automatic conveyor feeder',
            'p3' => 'The P3 with the automatic conveyor feeder is a Class 4 laser. xTool requires training, oversight by a laser safety officer, a controlled work area and goggles suitable for the lasers in use, including 10.6 µm radiation. Do not use this configuration in public places or around children or untrained people.',
            'sources' => 'Manufacturer instructions and information',
            'notice_heading' => 'Important safety information',
            'notice_f2' => 'The F2 is a Class 4 laser. xTool requires protective goggles even with the enclosure closed, training and laser safety officer oversight. Do not use it in public or residential areas, or around children or untrained people.',
            'notice_ultra' => 'The F2 Ultra is available in Class 4 versions for professional use. Check your model: xTool requires training, laser safety officer oversight and eye protection selected according to the instructions.',
            'notice_uv' => 'The F2 Ultra UV is available in versions with different safety classes. Check the model, instructions and required protection; it does not operate under Class 1 conditions with the RA3.',
            'notice_p3' => 'The P3 with the automatic conveyor feeder is a Class 4 laser and requires additional safeguards, training and suitable protective goggles.',
            'notice_ra3' => 'According to xTool, the F2 and F2 Ultra UV do not operate under Class 1 conditions with the RA3. Check your machine’s instructions and required safeguards.',
            'notice_duo' => 'This bundle contains different laser models. Check the instructions and required protection for each machine; F2 Ultra UV versions have different safety classes.',
            'more' => 'Read the safety information',
        ],
        'de' => [
            'title' => 'Sicherheit beim Lasern',
            'intro' => 'Laser können Augen und Haut dauerhaft schädigen sowie Brände und gesundheitsschädliche Dämpfe verursachen. Lesen Sie vor Kauf und Gebrauch die Anleitung Ihrer genauen Geräteversion und aller verwendeten Zubehörteile.',
            'general_heading' => 'Arbeitsplatz vorbereiten',
            'general' => 'Wählen Sie Schutzmaßnahmen, Augenschutz und Absaugung passend zu Gerät, Material und Anwendung. Blicken Sie auch mit Schutzbrille nie direkt in den Strahl oder seine Reflexionen. Betreiben Sie kein Gerät mit beschädigter Abdeckung oder defekter Verriegelung und umgehen Sie keine Schutzvorrichtungen. Lassen Sie einen laufenden Laser nie unbeaufsichtigt.',
            'materials_heading' => 'Materialien und Dämpfe',
            'materials' => 'Schneiden oder gravieren Sie PVC, PTFE oder Materialien unbekannter Zusammensetzung nicht mit dem Laser. Prüfen Sie die Hinweise der Material- und Gerätehersteller. Sorgen Sie für eine zum Material und den örtlichen Vorschriften passende Absaugung; ein Filter allein beseitigt nicht jede Gefahr.',
            'f2_heading' => 'xTool F2',
            'f2_package' => 'Laut xTool-FAQ ist beim Gerät selbst keine Schutzbrille enthalten. Prüfen Sie den Lieferumfang des gewählten Sets.',
            'f2' => 'Der F2 ist ein Laser der Klasse 4 für den professionellen Einsatz. xTool verlangt Schulung, Aufsicht durch einen Laserschutzbeauftragten und eine Schutzbrille für 445 ± 15 nm, 655 ± 5 nm und 1064 ± 5 nm. Die Schutzbrille ist auch bei geschlossener Abdeckung erforderlich. Den F2 nicht an öffentlichen Orten, in Wohnbereichen oder in Gegenwart von Kindern oder ungeschulten Personen verwenden.',
            'ultra_heading' => 'xTool F2 Ultra',
            'ultra' => 'Die Anforderungen hängen von der Geräteversion ab. Die Anleitung der Modelle MXF-K003-003 und MXF-K004-003 verlangt Augenschutz gegen Strahlung mit 445 ± 15 nm und 1064 ± 5 nm. Die Schutzbrillen-Tabelle von xTool berücksichtigt auch den 655 ± 5 nm-Anzeigelaser. Wählen Sie Schutzbrille und weitere Schutzmaßnahmen nach der Anleitung Ihres genauen Modells und der Gefährdungsbeurteilung.',
            'uv_heading' => 'xTool F2 Ultra UV und RA3',
            'uv' => 'Der F2 Ultra UV ist in Versionen mit unterschiedlichen Sicherheitsklassen erhältlich. Prüfen Sie Modellnummer und Anleitung und wählen Sie den Schutz für die verwendeten Quellen. Laut xTool arbeiten F2 und F2 Ultra UV mit dem RA3 nicht unter Klasse-1-Bedingungen.',
            'p3_heading' => 'xTool P3 mit automatischer Förderband-Zuführung',
            'p3' => 'Der P3 mit automatischer Förderband-Zuführung ist ein Laser der Klasse 4. xTool verlangt Schulung, Aufsicht durch einen Laserschutzbeauftragten, einen gesicherten Arbeitsbereich und eine zu den verwendeten Lasern passende Schutzbrille, einschließlich 10,6 µm. Diese Konfiguration nicht an öffentlichen Orten oder in Gegenwart von Kindern oder ungeschulten Personen verwenden.',
            'sources' => 'Anleitungen und Informationen des Herstellers',
            'notice_heading' => 'Wichtige Sicherheitshinweise',
            'notice_f2' => 'Der F2 ist ein Laser der Klasse 4. xTool verlangt auch bei geschlossener Abdeckung eine Schutzbrille sowie Schulung und Aufsicht durch einen Laserschutzbeauftragten. Nicht an öffentlichen Orten, in Wohnbereichen oder in Gegenwart von Kindern oder ungeschulten Personen verwenden.',
            'notice_ultra' => 'Der F2 Ultra ist in Klasse-4-Versionen für den professionellen Einsatz erhältlich. Prüfen Sie Ihr Modell: xTool verlangt Schulung, Aufsicht durch einen Laserschutzbeauftragten und Augenschutz nach Anleitung.',
            'notice_uv' => 'Den F2 Ultra UV gibt es in Versionen mit unterschiedlichen Sicherheitsklassen. Prüfen Sie Modell, Anleitung und erforderlichen Schutz; mit RA3 arbeitet er nicht unter Klasse-1-Bedingungen.',
            'notice_p3' => 'Der P3 mit automatischer Förderband-Zuführung ist ein Laser der Klasse 4 und erfordert zusätzliche Schutzmaßnahmen, Schulung und eine geeignete Schutzbrille.',
            'notice_ra3' => 'Laut xTool arbeiten F2 und F2 Ultra UV mit dem RA3 nicht unter Klasse-1-Bedingungen. Prüfen Sie die Anleitung und die erforderlichen Schutzmaßnahmen.',
            'notice_duo' => 'Das Set enthält verschiedene Lasermodelle. Prüfen Sie Anleitung und erforderlichen Schutz für jedes Gerät; die F2-Ultra-UV-Versionen haben unterschiedliche Sicherheitsklassen.',
            'more' => 'Sicherheitshinweise lesen',
        ],
        'ru' => [
            'title' => 'Безопасная работа с лазером',
            'intro' => 'Лазер может необратимо повредить глаза и кожу, вызвать пожар и образование вредных испарений. Перед покупкой и использованием изучите инструкцию именно вашей версии устройства и всех применяемых аксессуаров.',
            'general_heading' => 'Подготовка рабочего места',
            'general' => 'Подберите защиту рабочего места, очки и вытяжку для устройства, материала и способа работы. Не смотрите прямо на луч или его отражения даже в очках. Не используйте устройство с повреждённым кожухом или неисправной блокировкой и не обходите защиту. Не оставляйте работающий лазер без присмотра.',
            'materials_heading' => 'Материалы и испарения',
            'materials' => 'Не режьте и не гравируйте лазером ПВХ, ПТФЭ или материалы неизвестного состава. Проверьте инструкции производителей материала и устройства. Обеспечьте вытяжку, подходящую для материала и соответствующую местным требованиям; один фильтр не устраняет все опасности.',
            'f2_heading' => 'xTool F2',
            'f2_package' => 'Согласно FAQ xTool, защитные очки не входят в комплект самого устройства. Проверьте состав выбранного набора.',
            'f2' => 'F2 — лазер класса 4 для профессионального применения. xTool требует обучения, контроля ответственного за лазерную безопасность и очков, защищающих от излучения 445 ± 15 нм, 655 ± 5 нм и 1064 ± 5 нм. Очки обязательны и при закрытом кожухе. Не используйте F2 в общественных местах, жилых зонах, рядом с детьми или необученными людьми.',
            'ultra_heading' => 'xTool F2 Ultra',
            'ultra' => 'Требования зависят от версии устройства. Инструкция моделей MXF-K003-003 и MXF-K004-003 требует защиты глаз от излучения 445 ± 15 нм и 1064 ± 5 нм. Таблица подбора очков xTool также учитывает указатель 655 ± 5 нм. Подбирайте очки и другие средства защиты по инструкции именно вашей модели и оценке рисков.',
            'uv_heading' => 'xTool F2 Ultra UV и RA3',
            'uv' => 'F2 Ultra UV выпускается в версиях с разными классами безопасности. Проверьте номер модели и инструкцию и подберите защиту для используемых источников. По данным xTool, F2 и F2 Ultra UV с RA3 не работают в условиях класса 1.',
            'p3_heading' => 'xTool P3 с автоматическим конвейерным податчиком',
            'p3' => 'P3 с автоматическим конвейерным податчиком — лазер класса 4. xTool требует обучения, контроля ответственного за лазерную безопасность, защищённого рабочего места и очков для используемых лазеров, в том числе для излучения 10,6 мкм. Не используйте эту конфигурацию в общественных местах, рядом с детьми или необученными людьми.',
            'sources' => 'Инструкции и информация производителя',
            'notice_heading' => 'Важная информация о безопасности',
            'notice_f2' => 'F2 — лазер класса 4. xTool требует защитных очков даже при закрытом кожухе, обучения и контроля ответственного за лазерную безопасность. Не используйте его в общественных местах, жилых зонах, рядом с детьми или необученными людьми.',
            'notice_ultra' => 'F2 Ultra выпускается в версиях класса 4 для профессионального применения. Проверьте модель: xTool требует обучения, контроля ответственного за лазерную безопасность и защиты глаз по инструкции.',
            'notice_uv' => 'F2 Ultra UV выпускается в версиях с разными классами безопасности. Проверьте модель, инструкцию и требуемую защиту; с RA3 он не работает в условиях класса 1.',
            'notice_p3' => 'P3 с автоматическим конвейерным податчиком — лазер класса 4; нужны дополнительные меры защиты, обучение и подходящие защитные очки.',
            'notice_ra3' => 'По данным xTool, F2 и F2 Ultra UV с RA3 не работают в условиях класса 1. Проверьте инструкцию устройства и необходимые меры защиты.',
            'notice_duo' => 'В комплект входят разные модели лазеров. Изучите инструкцию и требования к защите каждого устройства; версии F2 Ultra UV имеют разные классы безопасности.',
            'more' => 'Прочитать информацию о безопасности',
        ],
        'uk' => [
            'title' => 'Безпечна робота з лазером',
            'intro' => 'Лазери можуть незворотно пошкодити очі та шкіру, спричинити пожежу й утворення шкідливих випарів. Перед купівлею та використанням ознайомтеся з інструкцією саме вашої версії пристрою та всіх використовуваних аксесуарів.',
            'general_heading' => 'Підготовка робочого місця',
            'general' => 'Доберіть захист робочого місця, окуляри та витяжку відповідно до пристрою, матеріалу й способу роботи. Не дивіться прямо на промінь або його відбиття навіть в окулярах. Не використовуйте пристрій із пошкодженим кожухом чи несправним блокуванням і не обходьте захисні механізми. Не залишайте лазер без нагляду під час роботи.',
            'materials_heading' => 'Матеріали та випари',
            'materials' => 'Не ріжте й не гравіюйте лазером ПВХ, ПТФЕ або матеріали невідомого складу. Перевірте інструкції виробників матеріалу та пристрою. Забезпечте витяжку, придатну для матеріалу й відповідну місцевим вимогам; сам фільтр не усуває всіх небезпек.',
            'f2_heading' => 'xTool F2',
            'f2_package' => 'Згідно з FAQ xTool, захисні окуляри не входять до комплекту самого пристрою. Перевірте склад вибраного набору.',
            'f2' => 'F2 — лазер класу 4 для професійного використання. xTool вимагає навчання, нагляду відповідального за лазерну безпеку та окулярів від випромінювання 445 ± 15 нм, 655 ± 5 нм і 1064 ± 5 нм. Окуляри потрібні також за закритого кожуха. Не використовуйте F2 у громадських місцях, житлових зонах, поруч із дітьми або ненавченими людьми.',
            'ultra_heading' => 'xTool F2 Ultra',
            'ultra' => 'Вимоги залежать від версії пристрою. Інструкція моделей MXF-K003-003 і MXF-K004-003 вимагає захисту очей від випромінювання 445 ± 15 нм і 1064 ± 5 нм. Таблиця добору окулярів xTool враховує також покажчик 655 ± 5 нм. Добирайте окуляри та інші засоби захисту за інструкцією саме вашої моделі та оцінкою ризиків.',
            'uv_heading' => 'xTool F2 Ultra UV та RA3',
            'uv' => 'F2 Ultra UV випускається у версіях із різними класами безпеки. Перевірте номер моделі та інструкцію і доберіть захист для використовуваних джерел. За даними xTool, F2 та F2 Ultra UV із RA3 не працюють в умовах класу 1.',
            'p3_heading' => 'xTool P3 з автоматичним конвеєрним подавачем',
            'p3' => 'P3 з автоматичним конвеєрним подавачем — лазер класу 4. xTool вимагає навчання, нагляду відповідального за лазерну безпеку, захищеного робочого місця та окулярів для використовуваних лазерів, зокрема для випромінювання 10,6 мкм. Не використовуйте цю конфігурацію в громадських місцях, поруч із дітьми або ненавченими людьми.',
            'sources' => 'Інструкції та інформація виробника',
            'notice_heading' => 'Важлива інформація про безпеку',
            'notice_f2' => 'F2 — лазер класу 4. xTool вимагає захисних окулярів навіть за закритого кожуха, навчання та нагляду відповідального за лазерну безпеку. Не використовуйте його в громадських місцях, житлових зонах, поруч із дітьми або ненавченими людьми.',
            'notice_ultra' => 'F2 Ultra випускається у версіях класу 4 для професійного використання. Перевірте модель: xTool вимагає навчання, нагляду відповідального за лазерну безпеку та захисту очей за інструкцією.',
            'notice_uv' => 'F2 Ultra UV випускається у версіях із різними класами безпеки. Перевірте модель, інструкцію та потрібний захист; із RA3 він не працює в умовах класу 1.',
            'notice_p3' => 'P3 з автоматичним конвеєрним подавачем — лазер класу 4; потрібні додаткові заходи захисту, навчання та відповідні захисні окуляри.',
            'notice_ra3' => 'За даними xTool, F2 та F2 Ultra UV із RA3 не працюють в умовах класу 1. Перевірте інструкцію пристрою та необхідні заходи захисту.',
            'notice_duo' => 'Комплект містить різні моделі лазерів. Перевірте інструкцію та потрібний захист для кожного пристрою; версії F2 Ultra UV мають різні класи безпеки.',
            'more' => 'Прочитати інформацію про безпеку',
        ],
    ];
    return $texts[$language] ?? $texts['en'];
}

/** Only the document paragraphs are editable; product and cart warnings stay independent. */
function lasery_xtool_safety_page_defaults($language) {
    $keys = ['intro', 'general', 'materials', 'f2', 'f2_package', 'ultra', 'uv', 'p3'];
    return array_intersect_key(lasery_xtool_safety_texts($language), array_flip($keys));
}

function lasery_xtool_safety_page_texts($language = '') {
    $language = $language !== '' ? $language : lasery_xtool_interface_language();
    $text = lasery_xtool_safety_texts($language);
    if (function_exists('lasery_xtool_apply_text_overrides')) {
        $text = array_merge($text, lasery_xtool_apply_text_overrides('safety', $language, lasery_xtool_safety_page_defaults($language)));
    }
    return $text;
}

/** A notice belongs to a source product, not a title that can change or resemble another model. */
function lasery_xtool_product_safety_notice($post_id, $language = '') {
    if (!defined('LASERY_XTOOL_SOURCE_ID')) {
        return '';
    }
    $keys = [
        '9163846877397' => 'notice_f2',
        '9009695293653' => 'notice_ultra',
        '9187692970197' => 'notice_uv',
        '9089720189141' => 'notice_p3',
        '9089525088469' => 'notice_p3',
        '9089336017109' => 'notice_ra3',
        '9341134569685' => 'notice_duo',
    ];
    $source = (string) get_post_meta($post_id, LASERY_XTOOL_SOURCE_ID, true);
    if (!isset($keys[$source])) {
        return '';
    }
    $text = lasery_xtool_safety_texts($language);
    return $text[$keys[$source]] . ($source === '9163846877397' ? ' ' . $text['f2_package'] : '');
}
