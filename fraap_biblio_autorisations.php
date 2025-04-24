<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Définit les autorisations du plugin Fraap : bibliographie
 *
 * @plugin     Fraap : bibliographie
 * @copyright  2022
 * @author     Christophe Le Drean
 * @licence    GNU/GPL
 * @package    SPIP\Fraap_biblio\Autorisations
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/*
 * Un fichier d'autorisations permet de regrouper
 * les fonctions d'autorisations de votre plugin
 */

/**
 * Fonction d'appel pour le pipeline
 * @pipeline autoriser */
function fraap_biblio_autoriser() {
}

/* Exemple
function autoriser_fraap_biblio_configurer_dist($faire, $type, $id, $qui, $opt) {
	// type est un objet (la plupart du temps) ou une chose.
	// autoriser('configurer', '_fraap_biblio') => $type = 'fraap_biblio'
	// au choix :
	return autoriser('webmestre', $type, $id, $qui, $opt); // seulement les webmestres
	return autoriser('configurer', '', $id, $qui, $opt); // seulement les administrateurs complets
	return $qui['statut'] == '0minirezo'; // seulement les administrateurs (même les restreints)
	// ...
}
*/

// -----------------
// Objet fbiblios

/**
 * Autorisation de voir un élément de menu (fbiblios)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_fbiblios_menu_dist($faire, $type, $id, $qui, $opt) {
	return true;
}

/**
 * Autorisation de voir (fbiblios)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_fbiblios_voir_dist($faire, $type, $id, $qui, $opt) {
	return true;
}

/**
 * Autorisation de voir (fbiblio)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_fbiblio_voir_dist($faire, $type, $id, $qui, $opt) {
	return true;
}

/**
 * Autorisation de créer (fbiblio)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_fbiblio_creer_dist($faire, $type, $id, $qui, $opt) {
	return in_array($qui['statut'], ['0minirezo', '1comite']) and sql_countsel('spip_rubriques') > 0;
}

/**
 * Autorisation de modifier (fbiblio)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_fbiblio_modifier_dist($faire, $type, $id, $qui, $opt) {
	return in_array($qui['statut'], ['0minirezo', '1comite']);
}

/**
 * Autorisation de supprimer (fbiblio)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_fbiblio_supprimer_dist($faire, $type, $id, $qui, $opt) {
	return $qui['statut'] == '0minirezo' and !$qui['restreint'];
}

/**
 * Autorisation de créer l'élément (fbiblio) dans une rubrique
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_rubrique_creerfbibliodans_dist($faire, $type, $id, $qui, $opt) {
	return $id and autoriser('voir', 'rubrique', $id) and autoriser('creer', 'fbiblio', $id);
}

/**
 * Autorisation de lier/délier l'élément (fbiblios)
 *
 * @param  string $faire Action demandée
 * @param  string $type  Type d'objet sur lequel appliquer l'action
 * @param  int    $id    Identifiant de l'objet
 * @param  array  $qui   Description de l'auteur demandant l'autorisation
 * @param  array  $opt   Options de cette autorisation
 * @return bool          true s'il a le droit, false sinon
 **/
function autoriser_associerfbiblios_dist($faire, $type, $id, $qui, $opt) {
	return $qui['statut'] == '0minirezo' and !$qui['restreint'];
}
