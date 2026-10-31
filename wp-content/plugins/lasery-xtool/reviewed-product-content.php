<?php
/** F2 is a Class 4 professional laser; follow xTool's current safety instructions. */
function lasery_xtool_f2_safety_text( $language ) {
	$texts = array(
		'pl' => 'F2 to laser klasy 4 do użytku profesjonalnego. xTool wymaga przeszkolenia i nadzoru osoby odpowiedzialnej za bezpieczeństwo laserowe oraz okularów chroniących przed promieniowaniem 445 ± 15 nm, 655 ± 5 nm i 1064 ± 5 nm. Nie używaj urządzenia w miejscach publicznych ani mieszkalnych, ani przy dzieciach lub osobach nieprzeszkolonych.',
		'en' => 'F2 is a Class 4 laser for professional use. xTool requires trained operators, oversight by a laser safety officer and goggles protecting against 445 ± 15 nm, 655 ± 5 nm and 1064 ± 5 nm radiation. Do not use it in public or residential areas, or around children or untrained people.',
		'de' => 'Der F2 ist ein Klasse-4-Laser für den professionellen Einsatz. xTool verlangt geschultes Personal, die Aufsicht durch einen Laserschutzbeauftragten und eine Schutzbrille für 445 ± 15 nm, 655 ± 5 nm und 1064 ± 5 nm. Nicht an öffentlichen Orten, in Wohnbereichen oder in Gegenwart von Kindern oder ungeschulten Personen verwenden.',
		'ru' => 'F2 — лазер класса 4 для профессионального применения. xTool требует обучения операторов, контроля ответственного за лазерную безопасность и защитных очков от излучения 445 ± 15 нм, 655 ± 5 нм и 1064 ± 5 нм. Не используйте устройство в общественных местах, жилых зонах или в присутствии детей и необученных людей.',
		'uk' => 'F2 — лазер класу 4 для професійного використання. xTool вимагає навчання операторів, нагляду відповідального за лазерну безпеку й захисних окулярів від випромінювання 445 ± 15 нм, 655 ± 5 нм і 1064 ± 5 нм. Не використовуйте пристрій у громадських місцях, житлових зонах або в присутності дітей і ненавчених людей.',
	);
	return isset( $texts[ $language ] ) ? $texts[ $language ] : $texts['en'];
}

/** A P3 becomes Class 4 when used with its conveyor feeder. */
function lasery_xtool_p3_conveyor_safety_text( $language ) {
	$texts = array(
		'pl' => 'P3 z podajnikiem taśmowym jest laserem klasy 4. Producent wymaga przeszkolenia, nadzoru osoby odpowiedzialnej za bezpieczeństwo laserowe i odpowiednio zabezpieczonego stanowiska. Urządzenia nie wolno używać w miejscach publicznych ani przy dzieciach lub osobach nieprzeszkolonych. Potrzebne są okulary ochronne dobrane do używanych laserów, w tym do fali 10,6 µm.',
		'en' => 'The P3 with the conveyor feeder is a Class 4 laser. xTool requires training, a laser safety officer and an appropriately controlled work area. Do not use it in public places or around children or untrained people. Wear protective goggles suitable for the lasers in use, including the 10.6 µm wavelength.',
		'de' => 'Der P3 mit Förderband-Zuführung ist ein Laser der Klasse 4. xTool verlangt Schulung, einen Laserschutzbeauftragten und einen entsprechend gesicherten Arbeitsbereich. Nicht an öffentlichen Orten oder in Gegenwart von Kindern oder ungeschulten Personen verwenden. Tragen Sie eine auf die verwendeten Laser abgestimmte Schutzbrille, einschließlich der Wellenlänge 10,6 µm.',
		'ru' => 'P3 с конвейерным податчиком — лазер класса 4. xTool требует обучения, назначения ответственного за лазерную безопасность и надлежащим образом защищённого рабочего места. Не используйте устройство в общественных местах, рядом с детьми или необученными людьми. Используйте защитные очки для применяемых лазеров, в том числе для длины волны 10,6 мкм.',
		'uk' => 'P3 з конвеєрним подавачем — лазер класу 4. xTool вимагає навчання, призначення відповідального за лазерну безпеку та належно захищеного робочого місця. Не використовуйте пристрій у громадських місцях, поруч із дітьми або ненавченими людьми. Використовуйте захисні окуляри для застосовуваних лазерів, зокрема для довжини хвилі 10,6 мкм.',
	);
	return isset( $texts[ $language ] ) ? $texts[ $language ] : $texts['en'];
}

/** Reviewed source descriptions used for new products with inaccurate supplier text. */
function lasery_xtool_reviewed_english_features( $source_id ) {
	if ( '9163846877397' === (string) $source_id ) {
		return array(
			'The 5 W infrared laser engraves metals and plastics.',
			'The 15 W diode laser engraves and cuts materials such as wood, acrylic and leather.',
			'A 50 MP camera helps position designs.',
			'Up to 6000 mm/s supports batch work in a compact design.',
			lasery_xtool_f2_safety_text( 'en' ),
		);
	}
	if ( '8309744009429' === (string) $source_id ) {
		return array(
			'The RA2 Pro supports roller, chuck, sphere and ring setups.',
			'Compatible with xTool S1, F2, F2 Ultra, F2 Ultra UV and F1 Ultra. Check the F2 Ultra connection cable: some sets need a compatible cable purchased separately.',
			'The RA2 Pro Plus includes the RA2 Pro and accessory kit for additional setups and focus alignment.',
		);
	}
	if ( '7954661376213' === (string) $source_id ) {
		return array(
			'Connects to the laser and can automatically trigger fire suppression.',
			'Manual activation is available with a button.',
			'Indicators show gas level, motor stall and operating status.',
			'After activation, check and replace CO₂ cylinders according to your version’s manual. The set works in a closed processing space, cannot extinguish every fire and does not replace supervision or a fire extinguisher. For the original Fire Safety Set with M2 and xTool Inline Duct Fan or xTool Smoke Purifier, a separate smart switch is required.',
			'Fire Safety Set 2 fits X1 and M2; Fire Safety Set fits M2, S1, F2 Ultra, F2 Ultra UV and F1 Ultra.',
		);
	}
	return null;
}

/** Keep imported content unless the supplier text for this product needs a reviewed fallback. */
function lasery_xtool_initial_product_content( $product ) {
	// SHA-256 of the sanitized xTool F2 description reviewed on 2026-10-31.
	// Changed source falls back to the reviewed summary instead of reusing unreviewed claims.
	if ( '9163846877397' === (string) $product['id'] && '59a5d9142dab4b2ff9f63372b5bb8fed50dd224ff3a7981f80c0940a3de78862' === hash( 'sha256', $product['content'] ) ) {
		// Keep the supplier's other product details when its known unsafe claims match exactly.
		$changes = array(
			'Live preview for precise placement and minimized material waste.' => 'Camera preview helps position the design.',
			'Portable &amp; Safe:' => 'Compact design:',
			'Compact, eye-safe design built for on-site personalization.' => 'Compact design for personalization projects.',
			'Just 4 steps from inspiration to production. Zero learning curve required.' => 'A streamlined workflow from design to production.',
		);
		$content = $product['content'];
		foreach ( $changes as $before => $after ) {
			if ( 1 !== substr_count( $content, $before ) ) {
				$content = '';
				break;
			}
			$content = str_replace( $before, $after, $content );
		}
		if ( '' !== $content ) {
			$notice = esc_html( lasery_xtool_f2_safety_text( 'en' ) );
			$links = '<a href="https://support.xtool.com/article/3424">xTool Safety First</a>; <a href="https://support.xtool.com/academy/lesson/2642?courseId=271">xTool LSO</a>';
			return $content . wp_kses_post( '<p>' . $notice . ' ' . $links . '.</p>' );
		}
	}
	if ( in_array( (string) $product['id'], array( '9089720189141', '9089525088469' ), true ) ) {
		$notice = esc_html( lasery_xtool_p3_conveyor_safety_text( 'en' ) );
		$link = '<a href="https://support.xtool.com/academy/lesson/2579?courseId=267">xTool safety instructions</a>';
		return $product['content'] . wp_kses_post( '<p>' . $notice . ' ' . $link . '.</p>' );
	}
	$items = lasery_xtool_reviewed_english_features( $product['id'] );
	if ( null === $items ) {
		return $product['content'];
	}
	return wp_kses_post( '<ul>' . implode( '', array_map( static function ( $item ) { return '<li>' . esc_html( $item ) . '</li>'; }, $items ) ) . '</ul>' );
}
