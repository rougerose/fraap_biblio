<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('base/abstract_sql');
include_spip('action/editer_objet');
include_spip('action/editer_liens');
include_spip('inc/autoriser');

function fraap_biblio_synchroniser($forcer = false) {
	$config_synchro = fraap_biblio_synchro_configurer($forcer);
	if (!$config_synchro) {
		return [
			'type' => 0,
			'message' => _T('fraap_biblio:cfg_message_erreur_noconfig'),
		]; // Erreur, on arrête ici.
	}

	if ($config_synchro['fbiblios']['action'] == 'synchro') {
		// Synchroniser les référénces (zitems -> fbiblios)
		$synchro = fraap_biblio_synchroniser_fbiblios($forcer, $config_synchro);

		if ($synchro == 1) {
			// Passer à l'action suivante
			$synchro = fraap_biblio_nettoyer_fbiblios();
			// effacer la config et mettre à jour la date de synchro après l'étape nettoyage
			ecrire_config('fraap_biblio_derniere_synchro', strval(strtotime('now')));
			effacer_config('fraap_biblio_synchro');
		}
	}

	if ($synchro < 0) {
		return ['type' => -1]; // continuer la synchro
	}
	if ($synchro == 0) {
		return [
			'type' => 0,
			'message' => _T('fbiblio:synchro_message_erreur'),
		];
	}
	if ($synchro == 1) {
		return ['type' => 1, 'message' => _T('fbiblio:synchro_message_ok')];
	}
}


function fraap_biblio_nettoyer_fbiblios() {
	$zitems = sql_allfetsel('id_zitem', 'spip_zitems', 'id_parent="0"');
	$in = sql_in('statut', ['prepa', 'prop', 'publie']);
	$fbiblios = sql_allfetsel('id_fbiblio, id_zitem', 'spip_fbiblios', $in);
	$items = [];
	$biblios = [];
	foreach ($zitems as $zitem) {
		$items[] = $zitem['id_zitem'];
	}
	foreach ($fbiblios as $fbiblio) {
		$biblios[] = $fbiblio['id_zitem'];
	}
	$diff = array_diff($biblios, $items);

	autoriser_exception('modifier', 'fbiblio', '*');
	foreach ($diff as $id_zitem) {
		$cle = array_search($id_zitem, array_column($fbiblios, 'id_zitem'));
		$id_objet = $fbiblios[$cle]['id_fbiblio'];
		fraap_biblio_dissocier_mots($id_objet);
		objet_modifier('fbiblio', $id_objet, ['statut' => 'poubelle']);
	}
	autoriser_exception('modifier', 'fbiblio', '*', false);
	return 1;
}


/**
 *
 * Contenu du tableau $config_synchro
 * $config_synchro = [
 *	'forcer' => true/false,
 *	'mediatheque => ['id_objet' => '', 'objet' => ''],
 *	'repertoire_mots' => [],
 *	'nb_max_synchro' => '',
 *	'date_derniere_synchro' => '',
 *	'fbiblios' => [
 *		'debut' => 0,
 *		'offset' => nb_max_synchro
 *		'etape' => 0,
 *		'solde' => 0,
 *		'total => 0,
 *		'action' => '',
 * ];
 */
function fraap_biblio_synchro_configurer($forcer) {
	$config_plugin = lire_config('fraap_biblio');
	$config_synchro = lire_config('fraap_biblio_synchro');
	$derniere_synchro = intval(lire_config('fraap_biblio_derniere_synchro'));

	$config_synchro = isset($config_synchro) ? $config_synchro : [];

	$config_synchro['forcer'] = isset($config_synchro['forcer']) ? $config_synchro['forcer'] : $forcer;

	// Rubrique Médiathèque
	if (!isset($config_synchro['mediatheque'])) {
		list($objet, $id_objet) = explode('|', $config_plugin['mediatheque'][0]);

		if ($id_objet == '' or $config_plugin['groupe'] == '') {
			return false;
		} else {
			$config_synchro['mediatheque'] = ['id_objet' => $id_objet, 'objet' => $objet];
		}
	}

	// Répertoire des mots-clés
	if (!isset($config_synchro['repertoire_mots'])) {
		$config_synchro['repertoire_mots'] = fraap_biblio_constituer_repertoire_mots($config_plugin['groupe']);
	}

	if (!$config_synchro['repertoire_mots']) {
		return false;
	}

	// Paliers de synchronisation
	if (!isset($config_synchro['nb_max_synchro'])) {
		$config_synchro['nb_max_synchro'] = $config_plugin['nb_max_synchro'];
	}

	// Config utilisée pour la synchro fbiblios
	if (!isset($config_synchro['fbiblios'])) {
		$config_synchro['fbiblios'] = [
			'debut' => 0,
			'offset' => $config_plugin['nb_max_synchro'],
			'solde' => 0,
			'etape' => 0,
			'total' => 0,
			'action' => 'synchro',
		];
	}

	// Date de la dernière synchro
	if (!isset($derniere_synchro)) {
		$config_synchro['derniere_synchro'] = strtotime('now');
		ecrire_config('fraap_biblio_derniere_synchro', strval($config_synchro['derniere_synchro']));
	} else {
		$config_synchro['derniere_synchro'] = $derniere_synchro;
	}

	return $config_synchro;
}


function fraap_biblio_synchroniser_fbiblios($forcer = false, $config = []) {
	$in = sql_in('statut', ['prepa', 'prop', 'publie']);
	/**
	 * Après installation du plugin, la table fbiblios est vide.
	 * On force la synchro.
	 */
	if (!$forcer and $config['fbiblios']['total'] == 0 and $total_fbiblios = intval(sql_countsel('spip_fbiblios', $in)) == 0) {
		$forcer = true;
		$config['forcer'] = $forcer;
	}

	if ($forcer) {
		$total_zitems = intval(sql_countsel('spip_zitems', 'id_parent="0"'));
		$config['fbiblios']['solde'] = $total_zitems;
		$config['fbiblios']['total'] = $total_zitems;
	} else {
		if ($config['fbiblios']['total'] == 0 and $config['fbiblios']['etape'] == 0) {
			$date_dernier_zitem = sql_getfetsel('updated', 'spip_zitems', 'id_parent="0"', '', 'updated DESC');

			if ($date_dernier_zitem) {
				$date_dernier_zitem = strtotime($date_dernier_zitem);
				if ($date_dernier_zitem > $config['derniere_synchro']) {
					$date_derniere_synchro = date('c', $config['derniere_synchro']);
					$total_zitems = intval(sql_countsel('spip_zitems', 'id_parent="0" AND updated > ' . sql_quote($date_derniere_synchro)));
					$config['fbiblios']['solde'] = $total_zitems;
					$config['fbiblios']['total'] = $total_zitems;
				}
			}
		}
	}

	if ($config['fbiblios']['total'] > 0 and $config['fbiblios']['action'] == 'synchro') {
		// Calculer le rang et le nombre de références à extraire à chaque étape
		$config['fbiblios']['debut'] = $config['fbiblios']['offset'] * $config['fbiblios']['etape'];
		$config['fbiblios']['solde'] = $config['fbiblios']['solde'] - $config['fbiblios']['offset'];
		// Pas de solde négatif.
		if ($config['fbiblios']['solde'] < 0) {
			$config['fbiblios']['solde'] = 0;
		}
		$config['fbiblios']['etape'] += 1;

		$limit = sql_quote($config['fbiblios']['debut']) . ',' . sql_quote($config['fbiblios']['offset']);

		// Si $date_derniere_synchro, alors la temporalité est à prendre en compte
		if (isset($date_derniere_synchro)) {
			$zitems = sql_allfetsel('id_zitem, titre, auteurs', 'spip_zitems', 'id_parent="0" AND updated > ' . sql_quote($date_derniere_synchro), '', 'updated DESC', $limit);
		} else {
			$zitems = sql_allfetsel('id_zitem, titre, auteurs', 'spip_zitems', 'id_parent="0"', '', 'updated DESC', $limit);
		}


		foreach ($zitems as $zitem) {
			fraap_biblio_ajouter_fbiblios($zitem, $config);
		}
	}

	if ($config['fbiblios']['solde'] > 0) {
		// Ne plus forcer
		$config['forcer'] = false;
		ecrire_config('fraap_biblio_synchro', $config);
		return -1; // continuer la synchro
	} else {
		// Solde à zero, nettoyer la table fbiblios
		$config['fbiblios']['action'] = 'nettoyer';
		ecrire_config('fraap_biblio_synchro', $config);
		return 1;
	}
}


function fraap_biblio_ajouter_fbiblios($zitem = [], $config = []) {
	$in = sql_in('statut', ['prepa', 'prop', 'publie']);
	$fbiblio = sql_fetsel('*', 'spip_fbiblios', 'id_zitem=' . sql_quote($zitem['id_zitem']) . ' AND ' . $in);
	$fbiblio_set = [
		'id_zitem' => $zitem['id_zitem'],
		'titre' => $zitem['titre'],
		'auteurs' => $zitem['auteurs'],
	];

	if (!$fbiblio) {
		$fbiblio['id_fbiblio'] = objet_inserer('fbiblio', $config['mediatheque']['id_objet'], $fbiblio_set);
	} else {
		// mettre à jour la référence
		autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio']);
		objet_modifier('fbiblio', $fbiblio['id_fbiblio'], $set);
		autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio'], false);

		fraap_biblio_dissocier_mots($fbiblio['id_fbiblio']);
	}

	$ztags = sql_allfetsel('tag', 'spip_ztags', 'id_zitem=' . sql_quote($zitem['id_zitem']));

	$ajouter_mots = fraap_biblio_ajouter_mots($fbiblio['id_fbiblio'], $ztags, $config['repertoire_mots']);

	$statut = null;

	if ($ajouter_mots) {
		if ($fbiblio['statut'] == 'prepa') {
			$statut = ['statut' => 'prop'];
		}
	} else {
		if ($fbiblio['statut'] == 'prop') {
			$statut = ['statut' => 'prepa'];
		}
		if ($fbiblio['statut'] == 'publie') {
			$statut = ['statut' => 'prepa'];
		}
	}

	if ($statut) {
		autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio']);
		objet_modifier('fbiblio', $fbiblio['id_fbiblio'], $statut);
		autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio'], false);
	}
}


function fraap_biblio_ajouter_mots($id_fbiblio = 0, $ztags = [], $repertoire_mots = []) {
	$res = ['index' => 0, 'categories' => 0, 'themes' => 0];

	if (intval($id_fbiblio) == 0) {
		return false; // erreur
	}

	if (is_array($ztags) and count($ztags) > 0) {
		foreach ($ztags as $ztag) {
			$column = null;
			$type_associer = '';
			$ztag_titre = fraap_biblio_normaliser_titre($ztag['tag']);

			preg_match('/(cat|mot)(=)/', $ztag_titre, $matches);

			if (count($matches) > 0) {
				list($type, $titre) =  explode('=', $ztag_titre);

				if ($type == 'cat') {
					$column = $repertoire_mots['categories'];
					$type_associer = 'categories';
					// Ajouter au total
					$res['index'] += 1;
				}

				if ($type == 'mot') {
					$column = $repertoire_mots['themes'];
					$type_associer = 'themes';
					// Ajouter au total
					$res['index'] += 1;
				}

				if ($column) {
					$cle = array_search($titre, array_column($column, 'titre'));
					if ($cle >= 0) {
						$associer = objet_associer(['mot' => $column[$cle]['id_mot']], ['fbiblio' => $id_fbiblio]);
						$res[$type_associer] += $associer;
					}
				}
			}
		}
	}

	/**
	 * Vérifier que :
	 * - le total attendu est non nul
	 * - au moins 1 mot-clé de type catégories a été ajouté
	 * - le total attendu est égal au nombre de mots ajoutés
	 */
	if ($res['index'] > 0 and $res['categories'] > 0 and $res['index'] == $res['categories'] + $res['themes']) {
		return true;
	} else {
		return false;
	}
}

function fraap_biblio_dissocier_mots($id_fbiblio) {
	// dissocier les mots-clés actuels
	$fbiblio_mots = sql_allfetsel('id_mot', 'spip_mots_liens', 'objet=' . sql_quote('fbiblio') . ' AND id_objet=' . $id_fbiblio);

	if (count($fbiblio_mots) > 0) {
		$ids = [];
		foreach ($fbiblio_mots as $mot) {
			$ids[] = $mot['id_mot'];
		}
		objet_dissocier(['mot' => $ids], ['fbiblio' => $id_fbiblio]);
	}
}


function fraap_biblio_constituer_repertoire_mots($id_groupe) {
	$repertoire = [
		'categories' => [],
		'themes' => [],
	];

	$mots = sql_allfetsel('id_mot, titre, id_parent', 'spip_mots', 'id_groupe=' . intval($id_groupe));

	foreach ($mots as $mot) {
		$donnees = [
			'id_mot' => $mot['id_mot'],
			'titre' => fraap_biblio_normaliser_titre($mot['titre'])
		];
		if ($mot['id_parent'] == 0) {
			$repertoire['categories'][] = $donnees;
		} else {
			$repertoire['themes'][] = $donnees;
		}
	}

	if (count($repertoire['categories']) == 0 or count($repertoire['themes']) == 0) {
		return false;
	}

	return $repertoire;
}

function fraap_biblio_normaliser_titre($titre) {
	return trim(fraap_biblio_supprimer_accent(mb_strtolower($titre)));
}


function fraap_biblio_supprimer_accent($texte) {
	$transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
	return $transliterator->transliterate($texte);
}
