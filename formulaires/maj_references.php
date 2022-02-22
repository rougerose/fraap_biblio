<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_maj_references_charger_dist() {
	include_spip('inc/config');
	$config_maj = lire_config('fraap_biblio_synchro');
	$config_fbiblios = lire_config('fraap_biblio_synchro_fbiblios');
	$maj = isset($config_maj) ? $config_maj : ['forcer' => false];
	$avancement = '';

	$nb = isset($config_fbiblios['encours']) ? $config_fbiblios['encours'] : 0;

	$avancement = _T('fbiblio:message_maj_en_cours', ['nb' => $nb]);

	$contexte = [
		'forcer' => $maj['forcer'] ? 'on' : '',
		'sync' => $nb ? 'on' : '',
		'avancement' => $avancement
	];

	return $contexte;
}

function formulaires_maj_references_traiter_dist() {
	include_spip('inc/fraap_biblio');
	$forcer = (_request('sync_complete')) ? true : false;
	$res = fraap_biblio_synchroniser($forcer);
	if ($res['type'] == 0) {
		return ['message_erreur' => $res['message']];
	}

	if ($res['type'] == 1) {
		return ['message_ok' => $res['message']];
	}
}
