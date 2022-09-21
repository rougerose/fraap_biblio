<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_maj_references_charger_dist() {
	include_spip('inc/config');
	$config_synchro = lire_config('fraap_biblio_synchro');
	$forcer = isset($config_synchro) ? $config_synchro['forcer'] : false;
	$avancement = '';

	if (isset($config_synchro['fbiblios']['action'])) {
		if ($config_synchro['fbiblios']['action'] == 'synchro') {
			$nb = isset($config_synchro['fbiblios']['solde']) ? $config_synchro['fbiblios']['solde'] : 0;
			$avancement = _T('fbiblio:synchro_message_maj_en_cours', ['nb' => $nb]);
		}

		if ($config_synchro['fbiblios']['action'] == 'nettoyer') {
			$avancement = _T('fbiblio:synchro_message_nettoyage_en_cours');
		}
	}

	$contexte = [
		'forcer' => $forcer ? 'on' : '',
		'sync' => isset($config_synchro['fbiblios']['action']) ? 'on' : '',
		'avancement' => $avancement,
	];

	return $contexte;
}

function formulaires_maj_references_traiter_dist() {
	include_spip('inc/fraap_biblio');
	$forcer = (_request('forcer')) ? true : false;
	$res = fraap_biblio_synchroniser($forcer);
	if ($res['type'] == 0) {
		return ['message_erreur' => $res['message']];
	}

	if ($res['type'] == 1) {
		return ['message_ok' => $res['message']];
	}
}
