<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_configurer_fraap_biblio_saisies() {
	$groupes_mediatheque = [];

	if ($groupes = sql_allfetsel('id_groupe, titre', 'spip_groupes_mots', 'tables_liees=' . sql_quote('fbiblios'))) {
		foreach ($groupes as $k => $groupe) {
			$groupes_mediatheque[$groupe['id_groupe']] = $groupe['titre'];
		}
	}

	$config = lire_config('fraap_biblio');
	$config['nb_max_synchro'] = 50;
	ecrire_config('fraap_biblio', $config);

	$saisies = [
		[
			'saisie' => 'selecteur_rubrique',
			'options' => [
				'nom' => 'mediatheque',
				'label' => '<:fraap_biblio:cfg_label_rubrique_mediatheque:>',
				'obligatoire' => 'oui',
				'explication' => '<:fraap_biblio:cfg_explication_rubrique_mediatheque:>',
			],
		],
		[
			'saisie' => 'selection',
			'options' => [
				'nom' => 'groupe',
				'label' => '<:fraap_biblio:cfg_label_groupe_mediatheque:>',
				'obligatoire' => 'oui',
				'data' => $groupes_mediatheque,
			],
		],
		[
			'saisie' => 'radio',
			'options' => [
				'nom' => 'synchro_automatique',
				'label' => 'Synchro auto',
				'obligatoire' => 'oui',
				'defaut' => 'non',
				'data' => [
					'oui' => 'oui',
					'non' => 'non',
				],
			],
		],
	];

	return $saisies;
}
