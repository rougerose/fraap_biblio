<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('base/abstract_sql');
include_spip('action/editer_objet');
include_spip('action/editer_liens');
include_spip('inc/autoriser');

function fraap_biblio_synchroniser() {
	$config_synchro = fraap_biblio_configurer_synchronisation();

	if (!$config_synchro) {
		// Pas de configuration, on arrête ici.
		return [
			'type' => 0,
			'message' => _T('fraap_biblio:cfg_message_erreur_noconfig')
		];
	}

	if ($config_synchro['fbiblios']['etape'] == 0) {
		if (intval(sql_countsel('spip_fbiblios', sql_in('statut', ['prepa', 'prop', 'publie']))) == 0) {
			$config_synchro['fbiblios']['action'] = 'install';
		}
	}

	if ($config_synchro['fbiblios']['action'] == 'synchro' or $config_synchro['fbiblios']['action'] == 'install') {
		$synchro = fraap_biblio_synchroniser_fbiblios($config_synchro);

		if ($synchro == -1) {
			return ['type' => -1]; // Poursuivre la synchro
		}

		// Synchro terminée : nettoyer
		if ($synchro == 1) {
			$synchro = fraap_biblio_nettoyer_fbiblios();
			ecrire_config('fraap_biblio_derniere_synchro', strval(strtotime('now')));
			effacer_config('fraap_biblio_synchro');
			return ['type' => 1, 'message' => _T('fbiblio:synchro_message_ok')];
		}
	}
}

function fraap_biblio_synchroniser_fbiblios($config) {
	$action = $config['fbiblios']['action'];
	$date_synchro = null;

	if ($config['fbiblios']['total'] == 0) {
		if ($action == 'install') {
			$total_zitems = intval(sql_countsel('spip_zitems', 'id_parent="0"'));
		}

		if ($action == 'synchro') {
			$zitem_update = sql_getfetsel('updated', 'spip_zitems', 'id_parent="0"', '', 'updated DESC');

			if (strtotime($zitem_update) >= $config['derniere_synchro']) {
				$date_synchro = date('c', $config['derniere_synchro']);
				$total_zitems = intval(sql_countsel('spip_zitems', 'id_parent="0" AND updated>=' . sql_quote($date_synchro)));
			}
		}

		$config['fbiblios']['total'] = $total_zitems;
	}


	if ($config['fbiblios']['solde'] == 0 and $config['fbiblios']['etape'] == 0) {
		$config['fbiblios']['solde'] = $total_zitems;
	}

	if ($config['fbiblios']['total'] > 0 and $action == 'install' or $action == 'synchro') {
		// Calculer le rang et le nombre de références à extraire à chaque étape
		$config['fbiblios']['debut'] = $config['fbiblios']['offset'] * $config['fbiblios']['etape'];
		$config['fbiblios']['solde'] = $config['fbiblios']['solde'] - $config['fbiblios']['offset'];

		// Pas de solde négatif
		if ($config['fbiblios']['solde'] < 0) {
			$config['fbiblios']['solde'] = 0;
		}

		// Incrémenter l'étape
		$config['fbiblios']['etape'] += 1;

		$limit = sql_quote($config['fbiblios']['debut']) . ',' . sql_quote($config['fbiblios']['offset']);

		if ($date_synchro) {
			$zitems = sql_allfetsel('id_zitem, titre, auteurs, resume, type_ref, annee', 'spip_zitems', 'id_parent="0" AND updated>=' . sql_quote($date_synchro), '', 'updated DESC', $limit);
		} else {
			$zitems = sql_allfetsel('id_zitem, titre, auteurs, resume, type_ref, annee', 'spip_zitems', 'id_parent="0"', '', '', $limit);
		}

		foreach ($zitems as $zitem) {
			fraap_biblio_ajouter_fbiblio($zitem, $config);
		}
	}

	if ($config['fbiblios']['solde'] > 0) {
		// poursuivre l'étape install ou synchro
		ecrire_config('fraap_biblio_synchro', $config);
		return -1;
	} else {
		// étape nettoyage
		$config['fbiblios']['action'] = 'nettoyer';
		ecrire_config('fraap_biblio_synchro', $config);
		return 1;
	}
}

function fraap_biblio_ajouter_fbiblio($zitem = [], $config = []) {
	$statut = null;

	$in = sql_in('statut', ['prepa', 'prop', 'publie']);
	$fbiblio = sql_fetsel('*', 'spip_fbiblios', 'id_zitem=' . sql_quote($zitem['id_zitem']) . ' AND ' . $in);

	$set = [
		'id_zitem' => $zitem['id_zitem'],
		'titre' => $zitem['titre'],
		'auteurs' => $zitem['auteurs'],
		'resume' => $zitem['resume'],
		'type_ref' => $zitem['type_ref'],
		'annee' => $zitem['annee'],
	];

	if (!$fbiblio) {
		$fbiblio['id_fbiblio'] = objet_inserer('fbiblio', $config['mediatheque']['id_objet'], $set);
	} else {
		autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio']);
		objet_modifier('fbiblio', $fbiblio['id_fbiblio'], $set);
		autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio'], false);

		fraap_biblio_dissocier_mots($fbiblio['id_fbiblio']);
	}

	if (!$fbiblio['statut']) {
		$fbiblio['statut'] = sql_getfetsel('statut', 'spip_fbiblios', 'id_fbiblio=' . $fbiblio['id_fbiblio']);
	}

	$ztags = sql_allfetsel('tag', 'spip_ztags', 'id_zitem=' . sql_quote($zitem['id_zitem']));

	if (count($ztags) > 0) {
		$ajouter_mots = fraap_biblio_ajouter_mots($fbiblio['id_fbiblio'], $ztags, $config['repertoire_mots']);
	}

	if ($ajouter_mots) {
		if ($fbiblio['statut'] == 'prepa' or $fbiblio['statut'] == 'prop') {
			$statut = ['statut' => 'publie'];
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


function fraap_biblio_configurer_synchronisation() {
	$config_plugin = lire_config('fraap_biblio');
	$config_synchro = lire_config('fraap_biblio_synchro');
	$derniere_synchro = intval(lire_config('fraap_biblio_derniere_synchro'));

	$config_synchro = isset($config_synchro) ? $config_synchro : [];

	// Identifier la rubrique Médiathèque
	if (!isset($config_synchro['mediatheque'])) {
		list($objet, $id_objet) = explode('|', $config_plugin['mediatheque'][0]);

		if ($id_objet == '' or $config_plugin['groupe'] == '') {
			return false;
		} else {
			$config_synchro['mediatheque'] = [
				'id_objet' => $id_objet,
				'objet' => $objet
			];
		}
	}

	// Répertoire de mots-clés
	if (!isset($config['repertoire_mots'])) {
		$config_synchro['repertoire_mots'] = fraap_biblio_repertorier_mots($config_plugin['groupe']);
	}
	if (!$config_synchro['repertoire_mots']) {
		return false;
	}

	// Paliers de synchronisation
	if (!isset($config_synchro['nb_max_synchro'])) {
		$config_synchro['nb_max_synchro'] = $config_plugin['nb_max_synchro'];
	}

	// Configuration utilisée pour la synchro des fbiblios
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
		$now = strtotime('nom');
		$config_synchro['derniere_synchro'] = $now;
		ecrire_config('fraap_biblio_derniere_synchro', strval($now));
	} else {
		$config_synchro['derniere_synchro'] = $derniere_synchro;
	}

	return $config_synchro;
}

function fraap_biblio_repertorier_mots($id_groupe) {
	$repertoire = [];
	$mots = sql_allfetsel('id_mot, titre', 'spip_mots', 'id_groupe_racine=' . intval($id_groupe));

	foreach ($mots as $mot) {
		$donnees = [
			'id_mot' => $mot['id_mot'],
			'titre' => fraap_biblio_normaliser_titre($mot['titre']),
		];
		$repertoire[] = $donnees;
	}

	if (count($repertoire) == 0) {
		return false;
	} else {
		return $repertoire;
	}
}

function fraap_biblio_ajouter_mots($id_fbiblio = 0, $ztags = [], $repertoire_mots = []) {
	$res = ['index' => 0, 'mots' => 0];

	if (intval($id_fbiblio) == 0) {
		return false; // erreur
	}

	if (is_array($ztags) and count($ztags) > 0) {
		foreach ($ztags as $ztag) {
			$ztag_titre = fraap_biblio_normaliser_titre($ztag['tag']);

			if (strpos($ztag_titre, 'mot=') !== false) {
				list($type, $titre) = explode('=', $ztag_titre);
				$res['index'] += 1;

				$cle = array_search($titre, array_column($repertoire_mots, 'titre'));
				if ($cle >= 0) {
					$associer = objet_associer(['mot' => $repertoire_mots[$cle]['id_mot']], ['fbiblio' => $id_fbiblio]);
					$res['mots'] += $associer;
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
	if ($res['index'] > 0 and $res['mots'] > 0 and $res['index'] == $res['mots']) {
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
	$repertoire = [];
	$mots = sql_allfetsel('id_mot, titre', 'spip_mots', 'id_groupe_racine=' . intval($id_groupe));

	foreach ($mots as $mot) {
		$donnees = [
			'id_mot' => $mot['id_mot'],
			'titre' => fraap_biblio_normaliser_titre($mot['titre'])
		];
		$repertoire[] = $donnees;
	}

	if (count($repertoire) == 0) {
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
