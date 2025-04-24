<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('base/abstract_sql');
include_spip('action/editer_objet');
include_spip('action/editer_liens');
include_spip('inc/autoriser');
include_spip('inc/config');

function fraap_biblio_peuplement_synchroniser() {
	// Récupérer la synchro ou l'établir s'il elle n'existe pas encore.
	$config_synchro = fraap_biblio_peuplement_configurer();

	// Pas de config, on arrête ici.
	if (count($config_synchro) === 0) {
		spip_log('Synchro en erreur : ' . print_r($config_synchro['synchro'], true), 'fbiblios' . _LOG_ERREUR);
		return [
			'resultat' => 0,
			'message' => _T('fraap_biblio:cfg_message_erreur_noconfig'),
		];
	}

	// Si debut = 0, vérifier si des fbiblios sont déjà enregistrés.
	// Si ce n'est pas le cas, noter que c'est une `install` (première synchro).
	if ($config_synchro['synchro']['debut'] == 0) {
		if (sql_countsel('spip_fbiblios', sql_in('statut', ['prepa', 'prop', 'publie'])) == 0) {
			$config_synchro['synchro']['etape'] = 'install';
		}
	}

	if (preg_match('/install|synchro/', $config_synchro['synchro']['etape'])) {
		$synchro = fraap_biblio_peuplement_ajouter_fbiblios($config_synchro);

		// Erreur
		if ($synchro == 0) {
			spip_log('Synchro en erreur : ' . print_r($config_synchro['synchro'], true), 'fbiblios' . _LOG_ERREUR);
			effacer_config('fraap_biblio_synchro');
			return [
				'resultat' => 0,
				'message' => _T('synchro_message_erreur'),
			];
		}

		// C'est fini pour cette étape
		if ($synchro == 1) {
			spip_log(
				'Synchro terminée, avant nettoyage : ' . print_r($config_synchro['synchro'], true),
				'fbiblios' . _LOG_INFO_IMPORTANTE
			);
			$config_synchro = lire_config('fraap_biblio_synchro');
			$config_synchro['synchro']['etape'] = 'nettoyer';
			ecrire_config('fraap_biblio_synchro', $config_synchro);
			return [
				'resultat' => -1,
				'message' => '',
			];

		}

		// Poursuivre l'étape synchro
		if ($synchro == -1) {
			spip_log('Synchro en cours : ' . print_r($config_synchro['synchro'], true), 'fbiblios' . _LOG_INFO_IMPORTANTE);
			return [
				'resultat' => -1,
				'message' => '',
			];
		}

	}

	if ($config_synchro['synchro']['etape'] == 'nettoyer') {
		$synchro = fraap_biblio_peuplement_supprimer_fbiblios_disparus($config_synchro);
		// Etape terminée
		if ($synchro == 1) {
			spip_log('Synchro terminée : ' . print_r($config_synchro['synchro'], true), 'fbiblios' . _LOG_INFO_IMPORTANTE);
			effacer_config('fraap_biblio_synchro');
			return [
				'resultat' => 1,
				'message' => _T('fbiblio:synchro_message_ok'),
			];
		}
	}

	// Si on arrive ici c'est qu'il y a une erreur
	spip_log('Synchro en erreur : ' . print_r($config_synchro['synchro'], true), 'fbiblios' . _LOG_ERREUR);

	return [
		'resultat' => 0,
		'message' => _T('synchro_message_erreur'),
	];
}

/**
 * @return array Tableau des données de configuration de la synchronisation
 * 	- `mediatheque`		: id_objet et objet de la rubrique Médiathèque
 *  - `groupe`			: identifiant du groupe de mots-clés de la Médiathèque
 *  - `nb_max_synchro`	: paliers de zitems à collecter depuis la base pour alimenter la mise à jour
 * 	- `repertoire_mots`	: tableau des mots-clés disponibles
 *  - `synchro`			: tableau des données et action relatif à la synchro
 *  	- `debut`		: à partir de quel nombre on collecte les données
 *  	- `offset`		: nombre maximum de zitems à collecter par palier
 *  	- `total`		: nombre total de zitems à synchroniser
 *  	- `solde`		: nombre de zitems restants à synchroniser
 *  	- `etape`		: action à réaliser, par défaut `synchro`
 */
function fraap_biblio_peuplement_configurer() {
	$config_plugin = lire_config('fraap_biblio');
	$config_synchro = lire_config('fraap_biblio_synchro') ?? [];

	// La rubrique et le groupe de mots-clés associés aux références sont nécessaires
	if ($config_plugin['mediatheque'] == '' || $config_plugin['groupe'] == '') {
		return [];
	}

	// Récupérer l'identifiant de la médiathèque des références
	if (!isset($config_synchro['mediatheque'])) {
		[$objet, $id_objet] = explode('|', $config_plugin['mediatheque'][0]);

		$config_synchro['mediatheque'] = [
			'id_objet' => $id_objet,
			'objet' => $objet,
		];
	}

	// Paliers de synchro
	if (!isset($config_synchro['nb_max_synchro'])) {
		$config_synchro['nb_max_synchro'] = $config_plugin['nb_max_synchro'];
	}

	// Répertoire des mots-clés
	if (!isset($config['repertoire_mots'])) {
		$config_synchro['repertoire_mots'] = fraap_biblio_peuplement_repertorier_mots($config_plugin['groupe']);
		if (count($config_synchro['repertoire_mots']) == 0) {
			return [];
		}
	}

	if (!isset($config_synchro['synchro'])) {
		$config_synchro['synchro'] = [
			'debut' => 0,
			'offset' => $config_synchro['nb_max_synchro'],
			'total' => 0,
			'solde' => 0,
			'etape' => 'synchro',
		];
	}

	return $config_synchro;

}

function fraap_biblio_peuplement_ajouter_fbiblios($config_synchro) {
	// Premier chargement (install) ou Mise à jour des données (synchro)
	if (preg_match('/install|synchro/', $config_synchro['synchro']['etape'])) {
		if ($config_synchro['synchro']['debut'] == 0) {
			$total = sql_countsel('spip_zitems', 'id_parent="0"');
			$solde = $total;
			$config_synchro['synchro']['solde'] = $solde;
			$config_synchro['synchro']['total'] = $total;
		}

		$limit = sql_quote($config_synchro['synchro']['debut']) . ',' . sql_quote($config_synchro['synchro']['offset']);

		$zitems = sql_allfetsel('*', 'spip_zitems', 'id_parent="0"', '', '', $limit);
	}

	if ($config_synchro['synchro']['etape'] == 'synchro') {
		if (is_array($zitems) and count($zitems) > 0) {
			// Ajouter les sources modifiées ou nouvelles
			foreach ($zitems as $zitem) {
				$updated = date_format(date_timestamp_set(new DateTime(), strtotime($zitem['updated'])), 'Y-m-d H:i:s');
				$id_zitem = $zitem['id_zitem'];
				$statut_in = sql_in('statut', ['prepa', 'prop', 'publie']);
				$fbiblio = sql_fetsel('*', 'spip_fbiblios', 'id_zitem=' . sql_quote($zitem['id_zitem']) . ' AND ' . $statut_in);

				// Si la référence n'existe, on l'ajoute.
				// Si la source a été mise à jour, on modifie la référence.
				if (!$fbiblio or $updated !== $fbiblio['updated']) {
					fraap_biblio_peuplement_modifier_fbiblio($zitem, $config_synchro);
				}
				$config_synchro['synchro']['debut']++;
				$config_synchro['synchro']['solde']--;
			}
			// Un lot a été inséré dans la base. On enregistre la configuration de cette synchro pour le tour suivant.
			ecrire_config('fraap_biblio_synchro', $config_synchro);

			// Il reste des données à insérer, on continue.
			if ($config_synchro['synchro']['solde'] > 0) {
				return -1;
			}

			// Le solde est null ou négatif (?), on arrête.
			if ($config_synchro['synchro']['solde'] <= 0) {
				return 1;
			}
		}
		// Rien à faire.
		return 0;
	}

	if ($config_synchro['synchro']['etape'] == 'install') {
		if (is_array($zitems) and count($zitems) > 0) {
			foreach ($zitems as $zitem) {
				fraap_biblio_peuplement_modifier_fbiblio($zitem, $config_synchro);
				$config_synchro['synchro']['debut']++;
				$config_synchro['synchro']['solde']--;
			}
			// Un lot a été inséré dans la base. On enregistre la configuration de cette synchro pour le tour suivant.
			ecrire_config('fraap_biblio_synchro', $config_synchro);

			// Il reste des données à insérer, on continue.
			if ($config_synchro['synchro']['solde'] > 0) {
				return -1;
			}

			// Le solde est null ou négatif (?), on arrête.
			if ($config_synchro['synchro']['solde'] <= 0) {
				return 1;
			}
		}
		// Rien à faire.
		return 0;
	}
}

function fraap_biblio_peuplement_modifier_fbiblio(array $zitem = [], array $config_synchro = []) {
	$mediatheque = intval($config_synchro['mediatheque']['id_objet']);
	$statut_in = sql_in('statut', ['prepa', 'prop', 'publie']);

	// année de publication : si cette donnée n'existe pas dans la source zitem, alors on prend l'année extrait de la colonne 'date_ajout'.
	if ($zitem['annee'] > 0) {
		$annee = $zitem['annee'];
	} else {
		$annee = date('Y', strtotime($zitem['date_ajout']));
	}

	// Convertir les dates updated et date_ajout, depuis format iso vers format mysql
	$updated = date_format(date_timestamp_set(new DateTime(), strtotime($zitem['updated'])), 'Y-m-d H:i:s');
	$date_ajout = date_format(date_timestamp_set(new DateTime(), strtotime($zitem['date_ajout'])), 'Y-m-d H:i:s');

	$set = [
		'id_zitem' => $zitem['id_zitem'],
		'titre' => $zitem['titre'],
		'auteurs' => $zitem['auteurs'],
		'resume' => $zitem['resume'],
		'type_ref' => $zitem['type_ref'],
		'updated' => $updated,
		'date_ajout' => $date_ajout,
		'annee' => $annee,
	];

	$fbiblio = sql_fetsel('*', 'spip_fbiblios', 'id_zitem=' . sql_quote($zitem['id_zitem']) . ' AND ' . $statut_in);

	if (!$fbiblio) {
		$id_fbiblio = objet_inserer('fbiblio', $mediatheque, $set);
	} else {
		$id_fbiblio = $fbiblio['id_fbiblio'];
	}

	autoriser_exception('modifier', 'fbiblio', $id_fbiblio);
	$modif = objet_modifier('fbiblio', $id_fbiblio, $set);
	autoriser_exception('modifier', 'fbiblio', $id_fbiblio, false);

	if ($modif) {
		spip_log("Fbiblio #{$id_fbiblio} n'a pas pu être modifié.", 'fbiblios' . _LOG_ERREUR);
	} else {
		// Récupérer le statut de la référence
		$statut = sql_getfetsel('statut', 'spip_fbiblios', 'id_fbiblio=' . $id_fbiblio);

		// Supprimer les mots-clés éventuellement associés
		fraap_biblio_peuplement_dissocier_mots($id_fbiblio);

		// Récupérer les mots-clés depuis la source
		$ztags = sql_allfetsel('tag', 'spip_ztags', 'id_zitem=' . sql_quote($zitem['id_zitem']));

		if (count($ztags) > 0) {
			$ajouter_mots = fraap_biblio_peuplement_ajouter_mots($id_fbiblio, $ztags, $config_synchro['repertoire_mots']);

			// Si fbiblio est lié à un mot-clé au moins, on change son statut en publié.
			if ($ajouter_mots) {
				if ($statut == 'publie') {
					// Ne pas modifier
					$statut = null;
				}

				if ($statut == 'prepa' or $statut == 'prop') {
					$statut = 'publie';
				}
			} else {
				if ($statut == 'prop') {
					$statut = 'prepa';
				}
				if ($statut == 'publie') {
					$statut = 'prepa';
				}
			}

			if ($statut) {
				autoriser_exception('modifier', 'fbiblio', $id_fbiblio);
				$statut_mod = objet_modifier('fbiblio', $id_fbiblio, ['statut' => $statut]);
				autoriser_exception('modifier', 'fbiblio', $id_fbiblio, false);
			}
		}
	}
}

function fraap_biblio_peuplement_supprimer_fbiblios_disparus($config_synchro) {
	$zitems = sql_allfetsel('id_zitem', 'spip_zitems', 'id_parent="0"');
	$in = sql_in('statut', ['prepa', 'prop', 'publie']);
	$fbiblios = sql_allfetsel('id_zitem, id_fbiblio', 'spip_fbiblios', $in);

	$zitems_id = array_column($zitems, 'id_zitem');
	$fbiblios_id = array_column($fbiblios, 'id_zitem');

	$diff = array_diff($fbiblios_id, $zitems_id);

	if (is_array($diff) and count($diff) > 0) {
		foreach ($diff as $key => $id_zitem) {
			$id_fbiblio = $fbiblios[$key]['id_fbiblio'];
			if ($id_fbiblio) {
				autoriser_exception('modifier', 'fbiblio', $id_fbiblio);
				$modifier_statut = objet_modifier('fbiblio', $id_fbiblio, ['statut' => 'poubelle']);
				autoriser_exception('modifier', 'fbiblio', $id_fbiblio, false);

				if ($modifier_statut == '') {
					fraap_biblio_peuplement_dissocier_mots($id_fbiblio);
				}
			}
		}
	}
	return 1;
}

function fraap_biblio_peuplement_ajouter_mots($id_fbiblio = 0, $ztags = [], $repertoire_mots = []) {
	$res = ['index' => 0, 'mots' => 0];

	if (intval($id_fbiblio) == 0) {
		return false; // erreur
	}

	if (is_array($ztags) and count($ztags) > 0) {
		foreach ($ztags as $ztag) {
			$ztag_titre = fraap_biblio_peuplement_normaliser_titre($ztag['tag']);

			if (strpos($ztag_titre, 'mot=') !== false) {
				[$type, $titre] = explode('=', $ztag_titre);
				++$res['index'];

				$cle = array_search($titre, array_column($repertoire_mots, 'titre'));
				if ($cle >= 0) {
					$associer = objet_associer(['mot' => $repertoire_mots[$cle]['id_mot']], ['fbiblio' => $id_fbiblio]);
					$res['mots'] += $associer;
				}
			}
		}
	}

	// Vérifier que le total attendu est non nul et qu'au moins 1 mot-clé de type catégories a été ajouté et que le total attendu est égal au nombre de mots ajoutés.
	if ($res['index'] > 0 and $res['mots'] > 0 and $res['index'] == $res['mots']) {
		return true;
	}
	return false;

}

function fraap_biblio_peuplement_dissocier_mots($id_fbiblio) {
	// dissocier les mots-clés actuels
	$fbiblio_mots = sql_allfetsel(
		'id_mot',
		'spip_mots_liens',
		'objet=' . sql_quote('fbiblio') . ' AND id_objet=' . $id_fbiblio
	);

	if (count($fbiblio_mots) > 0) {
		$ids = [];
		foreach ($fbiblio_mots as $mot) {
			$ids[] = $mot['id_mot'];
		}
		objet_dissocier(['mot' => $ids], ['fbiblio' => $id_fbiblio]);
	}
}

function fraap_biblio_peuplement_repertorier_mots($id_groupe = 0) {
	$repertoire = [];
	$mots = sql_allfetsel('id_mot, titre', 'spip_mots', 'id_groupe_racine=' . intval($id_groupe));

	foreach ($mots as $mot) {
		$repertoire[] = [
			'id_mot' => $mot['id_mot'],
			'titre' => fraap_biblio_peuplement_normaliser_titre($mot['titre']),
		];
	}

	if (count($repertoire) == 0) {
		return false;
	}
	return $repertoire;
}

function fraap_biblio_peuplement_normaliser_titre($titre) {
	return trim(fraap_biblio_peuplement_supprimer_accent(mb_strtolower($titre)));
}

function fraap_biblio_peuplement_supprimer_accent($texte) {
	$transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
	return $transliterator->transliterate($texte);
}
