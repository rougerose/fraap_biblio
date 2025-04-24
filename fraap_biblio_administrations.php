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
	include_spip('inc/config');
	$maj['create'] = [
		['maj_tables', ['spip_fbiblios']],
		['ecrire_config', 'fraap_biblio', ['nb_max_synchro' => 50, 'mediatheque' => '', 'groupe' => '']],
	];

	$maj['1.0.1'] = [
		['sql_alter', 'TABLE spip_fbiblios ADD updated DATETIME DEFAULT "0000-00-00 00:00:00" NOT NULL'],
		['sql_alter', 'TABLE spip_fbiblios ADD date_ajout DATETIME DEFAULT "0000-00-00 00:00:00" NOT NULL'],
		['fraap_biblio_peupler_dates'],
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
	sql_drop_table('spip_fbiblios');
	// sql_drop_table('spip_fbiblios_liens');

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

function fraap_biblio_peupler_dates() {
	$zitems = sql_allfetsel('id_zitem, updated, date_ajout', 'spip_zitems', 'id_parent="0"');

	if (count($zitems) > 0) {
		foreach ($zitems as $zitem) {
			// Convertir les dates ISO -> date msyql
			$updated = date_format(date_timestamp_set(new DateTime(), strtotime($zitem['updated'])), 'Y-m-d H:i:s');
			$date_ajout = date_format(date_timestamp_set(new DateTime(), strtotime($zitem['date_ajout'])), 'Y-m-d H:i:s');

			$data = [
				'updated' => $updated,
				'date_ajout' => $date_ajout,
			];

			// S'il y a eu des ratés dans la synchro, les données peuvent être multiples.
			$fbiblios = sql_allfetsel('id_fbiblio', 'spip_fbiblios', 'id_zitem=' . sql_quote($zitem['id_zitem']));

			$liste_fbiblios = [];

			foreach ($fbiblios as $fbiblio) {
				$liste_fbiblios[] = $fbiblio['id_fbiblio'];
			}

			sql_updateq('spip_fbiblios', $data, sql_in('id_fbiblio', $liste_fbiblios));

		}
	}
}
