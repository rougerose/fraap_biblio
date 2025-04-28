<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_synchroniser_fbiblios_charger() {
	$valeurs = [
		'synchroniser' => '',
		'avancement' => '',
	];

	$config_synchro = lire_config('fraap_biblio_synchro');
	$avancement = '';

	if (isset($config_synchro['synchro']['etape'])) {
		if (preg_match('/install|synchro/', $config_synchro['synchro']['etape'])) {
			if (isset($config_synchro['synchro']['solde'])) {
				$nb = $config_synchro['synchro']['solde'];
			} else {
				$nb = 0;
			}
			$avancement = _T('fbiblio:synchro_message_maj_en_cours', ['nb' => $nb]);
		}

		if ($config_synchro['synchro']['etape'] == 'nettoyer') {
			$avancement = _T('fbiblio:synchro_message_nettoyage_en_cours');
		}
	}

	$valeurs = [
		'sync' => isset($config_synchro['synchro']['etape']) ? 'on' : '',
		'avancement' => $avancement,
	];
	return $valeurs;
}

function formulaires_synchroniser_fbiblios_verifier() {
	$erreurs = [];
	$config = lire_config('fraap_biblio');

	if ($config['mediatheque'] == '' or $config['groupe'] == '') {
		$erreurs['message_erreur'] = 'Veuillez configurer la Médiathèque';
	}

	return $erreurs;
}

function formulaires_synchroniser_fbiblios_traiter() {
	include_spip('inc/fraap_biblio_peuplement');
	$retour = [
		'message_ok' => '',
		'message_erreur' => '',
	];

	$resultat = fraap_biblio_peuplement_synchroniser();

	if ($resultat['resultat'] == 0) {
		$retour = [
			'message_ok' => '',
			'message_erreur' => $resultat['message'],
		];
	}

	if ($resultat['resultat'] > 0) {
		$retour = [
			'message_ok' => $resultat['message'],
			'message_erreur' => '',
		];
	}

	return $retour;
}
