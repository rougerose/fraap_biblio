<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function genie_fraap_biblio_synchroniser($t) {
	include_spip('inc/fraap_biblio_peuplement');
	include_spip('inc/config');
	$config = lire_config('fraap_biblio/synchro_automatique');
	if ($config == 'oui') {
		$synchroniser = fraap_biblio_peuplement_synchroniser();
		if ($synchroniser['resultat'] < 0) {
			$t = time() * -1;
		} else {
			$t = $synchroniser['resultat'];
		}
		spip_log('Génie : ' . $t, 'fbiblios' . _LOG_INFO_IMPORTANTE);

	} else {
		$t = 0;
		spip_log('Génie : Pas de synchro', 'fbiblios' . _LOG_INFO_IMPORTANTE);
	}
	return $t;
}
