<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/config');

function formulaires_effacer_config_saisies() {
	$config_synchro = lire_config('fraap_biblio_synchro');
	$val = '';

	if ($config_synchro) {
		$val = 'Config présente';
	}
	$saisies = [
		[
			'saisie' => 'input',
			'options' => [
				'nom' => 'config',
				'label' => 'Config à effacer ?',
				'obligatoire' => 'non',
				'defaut' => $val,
				'disable' => 'oui',
			]
		]
	];

	return $saisies;
}

function formulaires_effacer_config_traiter() {
	$config_synchro = lire_config('fraap_biblio_synchro');
	$res = [];
	if ($config_synchro) {
		effacer_config('fraap_biblio_synchro');
		$res['message_ok'] = 'Config de synchro effacée';
	} else {
		$res['message_ok'] = 'Rien à effacer';
	}
	return $res;
}
