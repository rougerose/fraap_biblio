<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


function fraap_biblio_synchroniser($forcer = false) {
	include_spip('inc/config');

	$config_synchro = lire_config('fraap_biblio_synchro');
	$config = lire_config('fraap_biblio');

	if (isset($config_synchro['id_rubrique'])) {
		$id_rubrique = $config_synchro['id_rubrique'];
		$objet = 'rubrique';
		$synchro['id_rubrique'] = $id_rubrique;
	} else {
		list($objet, $id_rubrique) = explode('|', $config['mediatheque'][0]);
	}

	// Vérifier que la configuration est complète
	if ($id_rubrique == '' or $config['groupe'] == '') {
		return [
			'type' => 0,
			'message' => _T('fraap_biblio:cfg_message_erreur_noconfig')
		];
	}

	if ($forcer) {
		$synchro = ['forcer' => true];
	} else {
		$synchro = isset($config_synchro) ? $config_synchro : ['forcer' => false];
	}

	if (isset($config_synchro['repertoire_mots'])) {
		$repertoire_mots = $config_synchro['repertoire_mots'];
	} else {
		$repertoire_mots = fraap_biblio_constituer_repertoire_mots($config['groupe']);
		$synchro['repertoire_mots'] = $repertoire_mots;
	}

	$row = [
		'repertoire_mots' => $repertoire_mots,
		'nb_max_synchro' => $config['nb_max_synchro'],
		'date_derniere_synchro' => isset($config['date_derniere_synchro']) ? $config['date_derniere_synchro'] : null,
	];

	$traitement = fraap_biblio_synchroniser_fbiblios($id_rubrique, $row, $synchro['forcer']);

	if ($traitement == 1) {
		effacer_config('fraap_biblio_synchro');
		return ['type' => 1, 'message' => _L('message_ok')];
	}

	if ($traitement == 0) {
		effacer_config('fraap_biblio_synchro');
		return ['type' => 0, 'message' => _L('message erreur')];
	}

	if ($traitement < 0) {
		ecrire_config('fraap_biblio_synchro', $synchro);
		return -1;
	}
}



function fraap_biblio_synchroniser_fbiblios($id_mediatheque = 0, $row = [], $forcer = false) {
	include_spip('base/abstract_sql');
	include_spip('action/editer_objet');
	include_spip('action/editer_liens');
	include_spip('inc/autoriser');

	$config_synchro_fbiblios = lire_config('fraap_biblio_synchro_fbiblios');
	// $fbiblio = [];
	$repertoire_mots = $row['repertoire_mots'];

	// Si aucune synchronisation connue, alors on force la mise à jour.
	if (!isset($row['date_derniere_synchro'])) {
		$forcer = true;
		$row['date_derniere_synchro'] = strtotime('now');
		ecrire_config('fraap_biblio/date_derniere_synchro', $row['date_derniere_synchro']);
	}

	if (isset($config_synchro_fbiblios)) {
		$synchro_fbiblios = $config_synchro_fbiblios;
	} else {
		$synchro_fbiblios = ['debut' => 0, 'offset' => $row['nb_max_synchro'], 'total' => 0, 'encours' => 0];
	}

	if ($forcer) {
		// Nombre total des références à synchroniser
		$nb_a_synchroniser = intval(sql_countsel('spip_zitems', 'id_parent="0"'));
		$synchro_fbiblios['forcer'] = true;
		$synchro_fbiblios['total'] = $nb_a_synchroniser;
	} else {
		$synchro_fbiblios['forcer'] = false;

		// Si total est à 0, c'est le début de l'étape de synchro
		// et vérifier combien de synchro à faire.
		if ($synchro_fbiblios['total'] == 0 and $synchro_fbiblios['encours'] == 0) {
			// La date de modification des références la plus récente
			$dernier_zitem_maj = sql_getfetsel('updated', 'spip_zitems', 'id_parent="0"', '', 'updated DESC');

			if ($dernier_zitem_maj) {
				$date_derniere_synchro = $row['date_derniere_synchro'];
				$date_dernier_zitem = strtotime($dernier_zitem_maj);

				if ($date_dernier_zitem > $date_derniere_synchro) {
					$date_iso_derniere_synchro = date('c', $date_derniere_synchro);
					$nb_a_synchroniser = intval(sql_countsel('spip_zitems', 'id_parent="0" AND updated > ' . sql_quote($date_iso_derniere_synchro)));

					// On ne synchronisera que les éléments nécessaires
					if ($nb_a_synchroniser > 0) {
						$synchro_fbiblios['total'] = $nb_a_synchroniser;
					}
				} else {
					// Mettre à jour la date de synchro
					$row['date_derniere_synchro'] = strtotime('now');
					ecrire_config('fraap_biblio/date_derniere_synchro', $row['date_derniere_synchro']);

					effacer_config('fraap_biblio_synchro_fbiblios');
					// Rien à faire
					return 1;
				}
			}
		}
	}

	/**
	 * Si le total est > à 50, on fractionne en plusieurs étapes.
	 */
	if ($synchro_fbiblios['offset'] < $synchro_fbiblios['total']) {
		$synchro_fbiblios['total'] = $synchro_fbiblios['total'] - $synchro_fbiblios['offset'];
	} else {
		$synchro_fbiblios['offset'] = $synchro_fbiblios['total'];
		$synchro_fbiblios['total'] = 0;
	}

	$synchro_fbiblios['encours'] += $synchro_fbiblios['offset'];

	$limit = sql_quote($synchro_fbiblios['debut']) . ',' . sql_quote($synchro_fbiblios['offset']);

	$zitems = sql_allfetsel('id_zitem, titre, auteurs', 'spip_zitems', 'id_parent="0"', '', 'updated DESC', $limit);

	foreach ($zitems as $item) {
		$synchro_tags = false;
		$fbiblio = sql_fetsel('*', 'spip_fbiblios', 'id_zitem=' . sql_quote($item['id_zitem']));

		$set = [
			'id_zitem' => $item['id_zitem'],
			'titre' => $item['titre'],
			'auteurs' => $item['auteurs']
		];

		$ztags = sql_allfetsel('tag', 'spip_ztags', 'id_zitem=' . sql_quote($item['id_zitem']));

		// Créer la référence bibliographique
		if (intval($fbiblio['id_fbiblio']) == 0) {
			$fbiblio['id_fbiblio'] = objet_inserer('fbiblio', $id_mediatheque, $set);

			// TODO
			// - nombre de tags liés à la référence
			// - nombre de mots-clés liés effectivement à la référence
			// => en fonction du résultat : publier ou dépublier ?

			$synchro_tags = fraap_biblio_synchroniser_mots($fbiblio['id_fbiblio'], $ztags, $repertoire_mots);

			/**
			 * Si la synchro des tags est correcte,
			 * alors on modifie le statut de prepa à prop
			 */
			if ($synchro_tags) {
				autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio']);
				$mod = objet_modifier('fbiblio', $fbiblio['id_fbiblio'], ['statut' => 'prop']);
				autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio'], false);
			}
		} else {
			// Modifier la référence déjà existante
			autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio']);
			objet_modifier('fbiblio', $fbiblio['id_fbiblio'], $set);
			autoriser_exception('modifier', 'fbiblio', $fbiblio['id_fbiblio'], false);

			// Supprimer tous les liens existants entre fbiblio -> mots-clés
			$mots = sql_allfetsel('id_mot', 'spip_mots_liens', 'objet=' . sql_quote('fbiblio') . ' AND id_objet=' . $fbiblio['id_fbiblio']);

			if (count($mots) > 0) {
				$ids = [];
				foreach ($mots as $mot) {
					$ids[] = $mot['id_mot'];
				}
				objet_dissocier(['mot' => $ids], ['fbiblio' => $fbiblio['id_fbiblio']]);
			}

			if (count($ztags) > 0) {
				$synchro_mots = fraap_biblio_synchroniser_mots($fbiblio['id_fbiblio'], $ztags, $repertoire_mots);
			}

			/**
			 * Modifier le statut ?
			 * - si mots = true et si statut = prepa | prop => statut = prop
			 * - si mots = true et si statut = publie => ne rien faire.
			 * - si mots = false et si statut = prepa | prop => statut = prepa
			 * - si mots = false et si statut = publie => statut = prepa
			 * - pour les autres statuts = ne rien faire.
			 */
			$statut = null;
			if ($synchro_mots) {
				if ($fbiblio['statut'] == 'prepa' or $fbiblio['statut'] == 'prop') {
					$statut = ['statut' => 'prop'];
				}
			} else {
				if ($fbiblio['statut'] == 'prepa' or $fbiblio['statut'] == 'prop') {
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
	}
	// Vérifier si des références restent à traiter
	if ($synchro_fbiblios['total'] > 0) {
		// Ne plus forcer
		$synchro_fbiblios['forcer'] = false;
		ecrire_config('fraap_biblio_synchro_fbiblios', $synchro_fbiblios);
		return -1; // Poursuivre le traitement
	} else {

	}
}



function fraap_biblio_synchroniser_mots($id_fbiblio, $ztags = [], $repertoire_mots = []) {
	$synchro = ['index' => 0, 'categories' => 0, 'themes' => 0];
	if (is_array($ztags) and count($ztags) > 0) {
		foreach ($ztags as $tag) {
			$column = [];
			$type_associer = '';
			$tag_titre = fraap_biblio_normaliser_titre($tag['tag']);
			preg_match('/(cat|mot)(=)/', $tag_titre, $matches);
			if (count($matches) > 0) {
				list($type, $titre) = explode('=', $tag_titre);

				if ($type == 'cat') {
					$column = $repertoire_mots['categories'];
					$type_associer = 'categories';
					// Ajouter au total attendu
					$synchro['index'] += 1;
				}

				if ($type == 'mot') {
					$column = $repertoire_mots['themes'];
					$type_associer = 'themes';
					// Ajouter au total attendu
					$synchro['index'] += 1;
				}

				if (count($column) > 0) {
					$cle = array_search($titre, array_column($column, 'titre')); // int ou null
					if ($cle) {
						$post_association = objet_associer(['mot' => $column[$cle]['id_mot']], ['fbiblio' => $id_fbiblio]); // 0 ou 1
						// Ajouter le résultat au tableau synchro
						$synchro[$type_associer] += $post_association;
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
	if ($synchro['index'] > 0 and $synchro['categories'] > 0 and $synchro['index'] == $synchro['categories'] + $synchro['themes']) {
		return true;
	} else {
		return false;
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

	return $repertoire;
}

function fraap_biblio_normaliser_titre($titre) {
	return trim(fraap_biblio_supprimer_accent(mb_strtolower($titre)));
}


function fraap_biblio_supprimer_accent($texte) {
	$transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');
	return $transliterator->transliterate($texte);
}
