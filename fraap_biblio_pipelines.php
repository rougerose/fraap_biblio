<?php
/**
 * Utilisations de pipelines par Fraap : bibliographie
 *
 * @plugin     Fraap : bibliographie
 * @copyright  2022
 * @author     Christophe Le Drean
 * @licence    GNU/GPL
 * @package    SPIP\Fraap_biblio\Pipelines
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


/*
 * Un fichier de pipelines permet de regrouper
 * les fonctions de branchement de votre plugin
 * sur des pipelines existants.
 */


/**
 * Ajouter les objets sur les vues des parents directs
 *
 * @pipeline affiche_enfants
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
**/
function fraap_biblio_affiche_enfants($flux) {
	if (
		$e = trouver_objet_exec($flux['args']['exec'])
		and $e['edition'] === false
	) {
		$id_objet = $flux['args']['id_objet'];

		if ($e['type'] === 'rubrique') {

			$flux['data'] .= recuperer_fond(
				'prive/objets/liste/fbiblios',
				array(
					'titre' => _T('fbiblio:titre_fbiblios_rubrique'),
					'id_rubrique' => $id_objet
				)
			);

			if (autoriser('creerfbibliodans', 'rubrique', $id_objet)) {
				include_spip('inc/presentation');
				$flux['data'] .= icone_verticale(
					_T('fbiblio:icone_creer_fbiblio'),
					generer_url_ecrire('fbiblio_edit', "id_rubrique=$id_objet"),
					'fbiblio-24.png',
					'new',
					'right'
				) . "<br class='nettoyeur' />";
			}

		}
	}
	return $flux;
}


/**
 * Ajout de contenu sur certaines pages,
 * notamment des formulaires de liaisons entre objets
 *
 * @pipeline affiche_milieu
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 */
function fraap_biblio_affiche_milieu($flux) {
	$texte = '';
	$e = trouver_objet_exec($flux['args']['exec']);



	// fbiblios sur les mots
	if (
		$e
		and !$e['edition']
		and in_array($e['type'], array('mot'))
	) {
		$texte .= recuperer_fond('prive/objets/editer/liens', array(
			'table_source' => 'fbiblios',
			'objet' => $e['type'],
			'id_objet' => $flux['args'][$e['id_table_objet']]
		));
	}

	if ($texte) {
		if ($p = strpos($flux['data'], '<!--affiche_milieu-->')) {
			$flux['data'] = substr_replace($flux['data'], $texte, $p, 0);
		} else {
			$flux['data'] .= $texte;
		}
	}

	return $flux;
}

/**
 * Afficher le nombre d'éléments dans les parents
 *
 * @pipeline boite_infos
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
**/
function fraap_biblio_boite_infos($flux) {
	if (isset($flux['args']['type']) and isset($flux['args']['id']) and $id = intval($flux['args']['id'])) {
		$texte = '';
		if ($flux['args']['type'] == 'rubrique' and $nb = sql_countsel('spip_fbiblios', array("statut='publie'", 'id_rubrique=' . $id))) {
			$texte .= '<div>' . singulier_ou_pluriel($nb, 'fbiblio:info_1_fbiblio', 'fbiblio:info_nb_fbiblios') . "</div>\n";
		}
		if ($texte and $p = strpos($flux['data'], '<!--nb_elements-->')) {
			$flux['data'] = substr_replace($flux['data'], $texte, $p, 0);
		}
	}
	return $flux;
}


/**
 * Compter les enfants d'un objet
 *
 * @pipeline objets_compte_enfants
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
**/
function fraap_biblio_objet_compte_enfants($flux) {
	if ($flux['args']['objet'] == 'rubrique' and $id_rubrique = intval($flux['args']['id_objet'])) {
		// juste les publiés ?
		if (array_key_exists('statut', $flux['args']) and ($flux['args']['statut'] == 'publie')) {
			$flux['data']['fbiblios'] = sql_countsel('spip_fbiblios', 'id_rubrique= ' . intval($id_rubrique) . " AND (statut = 'publie')");
		} else {
			$flux['data']['fbiblios'] = sql_countsel('spip_fbiblios', 'id_rubrique= ' . intval($id_rubrique) . " AND (statut <> 'poubelle')");
		}
	}

	return $flux;
}


/**
 * Optimiser la base de données
 *
 * Supprime les liens orphelins de l'objet vers quelqu'un et de quelqu'un vers l'objet.
 * Supprime les objets à la poubelle.
 *
 * @pipeline optimiser_base_disparus
 * @param  array $flux Données du pipeline
 * @return array       Données du pipeline
 */
function fraap_biblio_optimiser_base_disparus($flux) {

	include_spip('action/editer_liens');
	$flux['data'] += objet_optimiser_liens(array('fbiblio'=>'*'), '*');

	sql_delete('spip_fbiblios', "statut='poubelle' AND maj < " . $flux['args']['date']);

	return $flux;
}

/**
 * Synchroniser la valeur de id secteur
 *
 * @pipeline trig_propager_les_secteurs
 * @param  string $flux Données du pipeline
 * @return string       Données du pipeline
**/
function fraap_biblio_trig_propager_les_secteurs($flux) {

	// synchroniser spip_fbiblios
	$r = sql_select(
		'A.id_fbiblio AS id, R.id_secteur AS secteur',
		'spip_fbiblios AS A, spip_rubriques AS R',
		'A.id_rubrique = R.id_rubrique AND A.id_secteur <> R.id_secteur'
	);
	while ($row = sql_fetch($r)) {
		sql_update('spip_fbiblios', array('id_secteur' => $row['secteur']), 'id_fbiblio=' . $row['id']);
	}

	return $flux;
}
