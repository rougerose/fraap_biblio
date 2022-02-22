<?php
/**
 * Gestion du formulaire de d'édition de fbiblio
 *
 * @plugin     Fraap : bibliographie
 * @copyright  2022
 * @author     Christophe Le Drean
 * @licence    GNU/GPL
 * @package    SPIP\Fraap_biblio\Formulaires
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/actions');
include_spip('inc/editer');

/**
 * Déclaration des saisies de fbiblio
 *
 * @param int|string $id_fbiblio
 *     Identifiant du fbiblio. 'new' pour un nouveau fbiblio.
 * @param int $id_rubrique
 *     Identifiant de l'objet parent (si connu)
 * @param string $retour
 *     URL de redirection après le traitement
 * @param string $associer_objet
 *     Éventuel `objet|x` indiquant de lier le fbiblio créé à cet objet,
 *     tel que `article|3`
 * @param int $lier_trad
 *     Identifiant éventuel d'un fbiblio source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du fbiblio, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return string
 *     Hash du formulaire
 */
function formulaires_editer_fbiblio_saisies_dist($id_fbiblio = 'new', $id_rubrique = 0, $retour = '', $associer_objet = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = '') {
	$saisies = array(
	);
	return $saisies;
}

/**
 * Identifier le formulaire en faisant abstraction des paramètres qui ne représentent pas l'objet edité
 *
 * @param int|string $id_fbiblio
 *     Identifiant du fbiblio. 'new' pour un nouveau fbiblio.
 * @param int $id_rubrique
 *     Identifiant de l'objet parent (si connu)
 * @param string $retour
 *     URL de redirection après le traitement
 * @param string $associer_objet
 *     Éventuel `objet|x` indiquant de lier le fbiblio créé à cet objet,
 *     tel que `article|3`
 * @param int $lier_trad
 *     Identifiant éventuel d'un fbiblio source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du fbiblio, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return string
 *     Hash du formulaire
 */
function formulaires_editer_fbiblio_identifier_dist($id_fbiblio = 'new', $id_rubrique = 0, $retour = '', $associer_objet = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = '') {
	return serialize(array(intval($id_fbiblio), $associer_objet));
}

/**
 * Chargement du formulaire d'édition de fbiblio
 *
 * Déclarer les champs postés et y intégrer les valeurs par défaut
 *
 * @uses formulaires_editer_objet_charger()
 *
 * @param int|string $id_fbiblio
 *     Identifiant du fbiblio. 'new' pour un nouveau fbiblio.
 * @param int $id_rubrique
 *     Identifiant de l'objet parent (si connu)
 * @param string $retour
 *     URL de redirection après le traitement
 * @param string $associer_objet
 *     Éventuel `objet|x` indiquant de lier le fbiblio créé à cet objet,
 *     tel que `article|3`
 * @param int $lier_trad
 *     Identifiant éventuel d'un fbiblio source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du fbiblio, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Environnement du formulaire
 */
function formulaires_editer_fbiblio_charger_dist($id_fbiblio = 'new', $id_rubrique = 0, $retour = '', $associer_objet = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = '') {
	$valeurs = formulaires_editer_objet_charger('fbiblio', $id_fbiblio, $id_rubrique, $lier_trad, $retour, $config_fonc, $row, $hidden);

	$valeurs['saisies'] = call_user_func_array('formulaires_editer_fbiblio_saisies_dist', func_get_args());
	return $valeurs;
}

/**
 * Vérifications du formulaire d'édition de fbiblio
 *
 * Vérifier les champs postés et signaler d'éventuelles erreurs
 *
 * @uses formulaires_editer_objet_verifier()
 *
 * @param int|string $id_fbiblio
 *     Identifiant du fbiblio. 'new' pour un nouveau fbiblio.
 * @param int $id_rubrique
 *     Identifiant de l'objet parent (si connu)
 * @param string $retour
 *     URL de redirection après le traitement
 * @param string $associer_objet
 *     Éventuel `objet|x` indiquant de lier le fbiblio créé à cet objet,
 *     tel que `article|3`
 * @param int $lier_trad
 *     Identifiant éventuel d'un fbiblio source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du fbiblio, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Tableau des erreurs
 */
function formulaires_editer_fbiblio_verifier_dist($id_fbiblio = 'new', $id_rubrique = 0, $retour = '', $associer_objet = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = '') {

	$erreurs = formulaires_editer_objet_verifier('fbiblio', $id_fbiblio);


	// Normaliser la rubrique si le champ n'est pas en erreur :
	// le picker ajax du sélecteur générique retourne un tableau de la forme array('rubrique|1')
	if (
		empty($erreurs['id_parent'])
		and $picker_id_parent = _request('id_parent')
		and is_numeric($id_parent = array_shift(picker_selected($picker_id_parent, 'rubrique')))
	) {
		set_request('id_parent', $id_parent);
	}

	return $erreurs;
}

/**
 * Traitement du formulaire d'édition de fbiblio
 *
 * Traiter les champs postés
 *
 * @uses formulaires_editer_objet_traiter()
 *
 * @param int|string $id_fbiblio
 *     Identifiant du fbiblio. 'new' pour un nouveau fbiblio.
 * @param int $id_rubrique
 *     Identifiant de l'objet parent (si connu)
 * @param string $retour
 *     URL de redirection après le traitement
 * @param string $associer_objet
 *     Éventuel `objet|x` indiquant de lier le fbiblio créé à cet objet,
 *     tel que `article|3`
 * @param int $lier_trad
 *     Identifiant éventuel d'un fbiblio source d'une traduction
 * @param string $config_fonc
 *     Nom de la fonction ajoutant des configurations particulières au formulaire
 * @param array $row
 *     Valeurs de la ligne SQL du fbiblio, si connu
 * @param string $hidden
 *     Contenu HTML ajouté en même temps que les champs cachés du formulaire.
 * @return array
 *     Retours des traitements
 */
function formulaires_editer_fbiblio_traiter_dist($id_fbiblio = 'new', $id_rubrique = 0, $retour = '', $associer_objet = '', $lier_trad = 0, $config_fonc = '', $row = array(), $hidden = '') {
	$retours = formulaires_editer_objet_traiter('fbiblio', $id_fbiblio, $id_rubrique, $lier_trad, $retour, $config_fonc, $row, $hidden);

	// Un lien a prendre en compte ?
	if ($associer_objet and $id_fbiblio = $retours['id_fbiblio']) {
		list($objet, $id_objet) = explode('|', $associer_objet);

		if ($objet and $id_objet and autoriser('modifier', $objet, $id_objet)) {
			include_spip('action/editer_liens');
			
			objet_associer(array('fbiblio' => $id_fbiblio), array($objet => $id_objet));
			
			if (isset($retours['redirect'])) {
				$retours['redirect'] = parametre_url($retours['redirect'], 'id_lien_ajoute', $id_fbiblio, '&');
			}
		}
	}

	return $retours;
}
