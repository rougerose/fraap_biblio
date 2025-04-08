<?php

/**
 * Fichier gérant l'installation et désinstallation du plugin Fraap : bibliographie
 *
 * @plugin     Fraap : bibliographie
 * @copyright  2022
 * @author     Christophe Le Drean
 * @licence    GNU/GPL
 * @package    SPIP\Fraap_biblio\Installation
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Fonction d'installation et de mise à jour du plugin Fraap : bibliographie.
 *
 * Vous pouvez :
 *
 * - créer la structure SQL,
 * - insérer du pre-contenu,
 * - installer des valeurs de configuration,
 * - mettre à jour la structure SQL
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 * @param string $version_cible
 *     Version du schéma de données dans ce plugin (déclaré dans paquet.xml)
 **/
function fraap_biblio_upgrade($nom_meta_base_version, $version_cible) {
	$maj = [];
	# quelques exemples
	# (que vous pouvez supprimer !)
	#
	# $maj['create'] = array(array('creer_base'));
	#
	# include_spip('inc/config')
	# $maj['create'] = array(
	#	array('maj_tables', array('spip_xx', 'spip_xx_liens')),
	#	array('ecrire_config', 'fraap_biblio', array('exemple' => "Texte de l'exemple"))
	#);
	#
	# $maj['1.1.0']  = array(array('sql_alter','TABLE spip_xx RENAME TO spip_yy'));
	# $maj['1.2.0']  = array(array('sql_alter','TABLE spip_xx DROP COLUMN id_auteur'));
	# $maj['1.3.0']  = array(
	#	array('sql_alter','TABLE spip_xx CHANGE numero numero int(11) default 0 NOT NULL'),
	#	array('sql_alter','TABLE spip_xx CHANGE texte petit_texte mediumtext NOT NULL default \'\''),
	# );
	# ...
	include_spip('inc/config');
	$maj['create'] = [
		['maj_tables', ['spip_fbiblios']],
		['ecrire_config', 'fraap_biblio', ['nb_max_synchro' => 50, 'mediatheque' => '', 'groupe' => '']],
	];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

/**
 * Fonction de désinstallation du plugin Fraap : bibliographie.
 *
 * Vous devez :
 *
 * - nettoyer toutes les données ajoutées par le plugin et son utilisation
 * - supprimer les tables et les champs créés par le plugin.
 *
 * @param string $nom_meta_base_version
 *     Nom de la meta informant de la version du schéma de données du plugin installé dans SPIP
 **/
function fraap_biblio_vider_tables($nom_meta_base_version) {
	# quelques exemples
	# (que vous pouvez supprimer !)
	# sql_drop_table('spip_xx');
	# sql_drop_table('spip_xx_liens');

	sql_drop_table('spip_fbiblios');
	sql_drop_table('spip_fbiblios_liens');

	# Nettoyer les liens courants (le génie optimiser_base_disparus se chargera de nettoyer toutes les tables de liens)
	sql_delete('spip_documents_liens', sql_in('objet', ['fbiblio']));
	sql_delete('spip_mots_liens', sql_in('objet', ['fbiblio']));
	sql_delete('spip_auteurs_liens', sql_in('objet', ['fbiblio']));
	# Nettoyer les versionnages et forums
	sql_delete('spip_versions', sql_in('objet', ['fbiblio']));
	sql_delete('spip_versions_fragments', sql_in('objet', ['fbiblio']));
	sql_delete('spip_forum', sql_in('objet', ['fbiblio']));

	effacer_meta($nom_meta_base_version);
	effacer_config('fraap_biblio');
	effacer_config('fraap_biblio_derniere_synchro');
	effacer_config('fraap_biblio_synchro');
}
