<?php
/** Consent for embedded xTool services and external product media. */
if (!defined('ABSPATH')) {
    exit;
}

/** Keep third-party embeds and media in post content inert until consent. */
function lasery_xtool_gate_external_content($content) {
    if (!is_string($content) || !class_exists('WP_HTML_Tag_Processor')) {
        return $content;
    }
    $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    $site_host = is_string($site_host) ? strtolower($site_host) : '';
    $is_external = static function ($url) use ($site_host) {
        if (!is_string($url) || $url === '') {
            return false;
        }
        // Browsers strip ASCII tabs and line breaks while parsing a URL.
        $url = trim(str_replace(["\t", "\n", "\r"], '', $url));
        // Browsers may interpret backslashes as URL separators even when
        // wp_parse_url does not see a host. Fail closed for these values.
        if (strpos($url, '\\') !== false) {
            return true;
        }
        $host = wp_parse_url($url, PHP_URL_HOST);
        return is_string($host) && strtolower($host) !== $site_host;
    };
    $processor = new WP_HTML_Tag_Processor($content);
    while ($processor->next_tag()) {
        $tag = $processor->get_tag();
        foreach (['src', 'poster', 'data'] as $attribute) {
            if ($attribute === 'data' && $tag !== 'OBJECT') {
                continue;
            }
            $value = $processor->get_attribute($attribute);
            if ($is_external($value)) {
                $processor->set_attribute('data-lasery-consent-' . $attribute, $value);
                $processor->remove_attribute($attribute);
            }
        }
        $srcset = $processor->get_attribute('srcset');
        $external_srcset = is_string($srcset) && strpos($srcset, '\\') !== false;
        $normalized_srcset = is_string($srcset) ? str_replace(["\t", "\n", "\r"], '', $srcset) : '';
        if ($normalized_srcset !== '' && preg_match_all('~(?:https?:)?//[^\s,]+~i', $normalized_srcset, $matches)) {
            foreach ($matches[0] as $url) {
                if ($is_external($url)) {
                    $external_srcset = true;
                    break;
                }
            }
        }
        if ($external_srcset) {
            $processor->set_attribute('data-lasery-consent-srcset', $srcset);
            $processor->remove_attribute('srcset');
        }
        $style = $processor->get_attribute('style');
        if (is_string($style) && preg_match('/url\s*\(/i', $style)) {
            $processor->set_attribute('data-lasery-consent-style', $style);
            $processor->remove_attribute('style');
        }
    }
    return $processor->get_updated_html();
}
add_filter('the_content', 'lasery_xtool_gate_external_content', 100);

function lasery_xtool_consent_texts() {
    $language = function_exists('lasery_xtool_interface_language') ? lasery_xtool_interface_language() : 'pl';
    $texts = [
        'pl' => [
            'title' => 'Ustawienia prywatności',
            'description' => 'Opcjonalne materiały xTool i zewnętrzne media w opisach produktów mogą przesyłać dane o połączeniu oraz korzystać z cookies. Załadujemy je tylko za Twoją zgodą. Wyboru możesz dokonać ponownie w stopce.',
            'accept' => 'Akceptuję', 'reject' => 'Odrzucam', 'privacy' => 'Polityka prywatności', 'settings' => 'Ustawienia prywatności',
            'frame_notice' => 'Aby wyświetlić formularz xTool, zaakceptuj zewnętrzne media w ustawieniach prywatności albo otwórz rezerwację osobno.',
            'noscript' => 'Do zmiany tych ustawień potrzebny jest JavaScript. Zewnętrzne materiały pozostają wyłączone.',
        ],
        'en' => [
            'title' => 'Privacy settings',
            'description' => 'Optional xTool content and external media in product descriptions may transmit connection data and use cookies. We load them only with your consent. You can change your choice in the footer.',
            'accept' => 'Accept', 'reject' => 'Reject', 'privacy' => 'Privacy policy', 'settings' => 'Privacy settings',
            'frame_notice' => 'To display the xTool form, accept external media in your privacy settings or open the booking page separately.',
            'noscript' => 'JavaScript is required to change these settings. External content remains disabled.',
        ],
        'de' => [
            'title' => 'Datenschutzeinstellungen',
            'description' => 'Optionale xTool-Inhalte und externe Medien in Produktbeschreibungen können Verbindungsdaten übermitteln und Cookies verwenden. Wir laden sie nur mit Ihrer Einwilligung. Sie können Ihre Auswahl in der Fußzeile ändern.',
            'accept' => 'Akzeptieren', 'reject' => 'Ablehnen', 'privacy' => 'Datenschutzerklärung', 'settings' => 'Datenschutzeinstellungen',
            'frame_notice' => 'Um das xTool-Formular anzuzeigen, akzeptieren Sie externe Medien in den Datenschutzeinstellungen oder öffnen Sie die Buchungsseite separat.',
            'noscript' => 'Zum Ändern dieser Einstellungen ist JavaScript erforderlich. Externe Inhalte bleiben deaktiviert.',
        ],
        'ru' => [
            'title' => 'Настройки конфиденциальности',
            'description' => 'Необязательные материалы xTool и внешние медиа в описаниях товаров могут передавать данные соединения и использовать cookies. Мы загружаем их только с вашего согласия. Изменить выбор можно внизу страницы.',
            'accept' => 'Принять', 'reject' => 'Отклонить', 'privacy' => 'Политика конфиденциальности', 'settings' => 'Настройки конфиденциальности',
            'frame_notice' => 'Чтобы показать форму xTool, разрешите внешние медиа в настройках конфиденциальности или откройте страницу бронирования отдельно.',
            'noscript' => 'Для изменения настроек нужен JavaScript. Внешние материалы остаются отключёнными.',
        ],
        'uk' => [
            'title' => 'Налаштування конфіденційності',
            'description' => 'Необов’язкові матеріали xTool і зовнішні медіа в описах товарів можуть передавати дані з’єднання та використовувати cookies. Ми завантажуємо їх лише за вашою згодою. Змінити вибір можна внизу сторінки.',
            'accept' => 'Прийняти', 'reject' => 'Відхилити', 'privacy' => 'Політика конфіденційності', 'settings' => 'Налаштування конфіденційності',
            'frame_notice' => 'Щоб показати форму xTool, дозвольте зовнішні медіа в налаштуваннях конфіденційності або відкрийте сторінку бронювання окремо.',
            'noscript' => 'Для зміни налаштувань потрібен JavaScript. Зовнішні матеріали залишаються вимкненими.',
        ],
    ];
    return isset($texts[$language]) ? $texts[$language] : $texts['pl'];
}

function lasery_xtool_render_consent_banner() {
    $text = lasery_xtool_consent_texts();
    $privacy_url = get_privacy_policy_url();
    if ($privacy_url && function_exists('lasery_xtool_language_url')) {
        $privacy_url = lasery_xtool_language_url($privacy_url);
    }
    ?>
    <section id="lasery-consent" class="lasery-consent" aria-labelledby="lasery-consent-title" data-lasery-consent-banner>
        <div class="lasery-consent-inner">
            <div>
                <h2 id="lasery-consent-title"><?php echo esc_html($text['title']); ?></h2>
                <p><?php echo esc_html($text['description']); ?> <?php if ($privacy_url) : ?><a href="<?php echo esc_url($privacy_url); ?>"><?php echo esc_html($text['privacy']); ?></a><?php endif; ?></p>
                <noscript><style>.lasery-consent{position:static;max-width:none;margin:16px;box-shadow:none}.lasery-consent-actions,.lasery-consent-settings,[data-lasery-consent-open]{display:none!important}</style><p><?php echo esc_html($text['noscript']); ?></p></noscript>
            </div>
            <div class="lasery-consent-actions">
                <button type="button" class="btn btn-primary btn-sm" data-lasery-consent-choice="deny"><?php echo esc_html($text['reject']); ?></button>
                <button type="button" class="btn btn-primary btn-sm" data-lasery-consent-choice="allow"><?php echo esc_html($text['accept']); ?></button>
            </div>
        </div>
    </section>
    <?php
}
add_action('wp_footer', 'lasery_xtool_render_consent_banner', 5);

function lasery_xtool_enqueue_consent() {
    wp_enqueue_script('lasery-xtool-consent', get_template_directory_uri() . '/assets/cookie-consent.js', [], wp_get_theme()->get('Version'), ['in_footer' => true, 'strategy' => 'defer']);
}
add_action('wp_enqueue_scripts', 'lasery_xtool_enqueue_consent', 20);
