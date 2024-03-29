<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/config');
include_spip('inc/fraap_biblio');

function formulaires_maj_references_charger_dist() {

	$config_synchro = lire_config('fraap_biblio_synchro');
	$avancement = '';

	if (isset($config_synchro['fbiblios']['action'])) {
		if ($config_synchro['fbiblios']['action'] == 'synchro' or $config_synchro['fbiblios']['action'] == 'install') {
			$nb = isset($config_synchro['fbiblios']['solde']) ? $config_synchro['fbiblios']['solde'] : 0;
			$avancement = _T('fbiblio:synchro_message_maj_en_cours', ['nb' => $nb]);
		}

		if ($config_synchro['fbiblios']['action'] == 'nettoyer') {
			$avancement = _T('fbiblio:synchro_message_nettoyage_en_cours');
		}
	}

	$contexte = [
		'sync' => isset($config_synchro['fbiblios']['action']) ? 'on' : '',
		'avancement' => $avancement,
	];

	return $contexte;
}

function formulaires_maj_references_verifier_dist() {
	$erreurs = [];
	$config = lire_config('fraap_biblio');
	if ($config['mediatheque'] == '' or $config['groupe'] == '') {
		$erreurs['message_erreur'] = 'Veuillez configurer la Médiathèque';
	}

	return $erreurs;
}

function formulaires_maj_references_traiter_dist() {
	$res = fraap_biblio_synchroniser();
	if ($res['type'] == 0) {
		return ['message_erreur' => $res['message']];
	}

	if ($res['type'] == 1) {
		return ['message_ok' => $res['message']];
	}
}
