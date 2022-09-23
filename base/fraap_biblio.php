<?php

/**
 * Déclarations relatives à la base de données
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


/**
 * Déclaration des alias de tables et filtres automatiques de champs
 *
 * @pipeline declarer_tables_interfaces
 * @param array $interfaces
 *     Déclarations d'interface pour le compilateur
 * @return array
 *     Déclarations d'interface pour le compilateur
 */
function fraap_biblio_declarer_tables_interfaces($interfaces) {

	$interfaces['table_des_tables']['fbiblios'] = 'fbiblios';

	return $interfaces;
}


/**
 * Déclaration des objets éditoriaux
 *
 * @pipeline declarer_tables_objets_sql
 * @param array $tables
 *     Description des tables
 * @return array
 *     Description complétée des tables
 */
function fraap_biblio_declarer_tables_objets_sql($tables) {

	$tables['spip_fbiblios'] = [
		'type' => 'fbiblio',
		'principale' => 'oui',
		'field' => [
			'id_fbiblio'         => 'bigint(21) NOT NULL',
			'id_rubrique'        => 'bigint(21) NOT NULL DEFAULT 0',
			'id_secteur'         => 'bigint(21) NOT NULL DEFAULT 0',
			'id_zitem'           => 'varchar(16) DEFAULT "" NOT NULL',
			'titre'              => 'text NOT NULL DEFAULT ""',
			'auteurs'            => 'text NOT NULL DEFAULT ""',
			'resume'             => "mediumtext DEFAULT '' NOT NULL",
			'type_ref'           => "varchar(255) DEFAULT '' NOT NULL",
			'annee'              => 'smallint(6)',
			'statut'             => 'varchar(20)  DEFAULT "0" NOT NULL',
			'maj'                => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		],
		'key' => [
			'PRIMARY KEY'        => 'id_fbiblio',
			'KEY id_rubrique'    => 'id_rubrique',
			'KEY id_secteur'     => 'id_secteur',
			'KEY statut'         => 'statut',
		],
		'titre' => 'titre AS titre, "" AS lang',
		 #'date' => '',
		'champs_editables'  => ['id_rubrique', 'id_secteur'],
		'champs_versionnes' => ['id_rubrique', 'id_secteur'],
		'rechercher_champs' => [],
		'tables_jointures'  => [],
		'statut_textes_instituer' => [
			'prepa'    => 'texte_statut_en_cours_redaction',
			'prop'     => 'texte_statut_propose_evaluation',
			'publie'   => 'texte_statut_publie',
			'refuse'   => 'texte_statut_refuse',
			'poubelle' => 'texte_statut_poubelle',
		],
		'statut' => [
			[
				'champ'     => 'statut',
				'publie'    => 'publie',
				'previsu'   => 'publie,prop,prepa',
				'post_date' => 'date',
				'exception' => ['statut','tout']
			]
		],
		'texte_changer_statut' => 'fbiblio:texte_changer_statut_fbiblio',


	];

	return $tables;
}


/**
 * Déclaration des tables secondaires (liaisons)
 *
 * @pipeline declarer_tables_auxiliaires
 * @param array $tables
 *     Description des tables
 * @return array
 *     Description complétée des tables
 */
// function fraap_biblio_declarer_tables_auxiliaires($tables) {

// 	$tables['spip_fbiblios_liens'] = [
// 		'field' => [
// 			'id_fbiblio'         => 'bigint(21) DEFAULT "0" NOT NULL',
// 			'id_objet'           => 'bigint(21) DEFAULT "0" NOT NULL',
// 			'objet'              => 'varchar(25) DEFAULT "" NOT NULL',
// 			'vu'                 => 'varchar(6) DEFAULT "non" NOT NULL',
// 		],
// 		'key' => [
// 			'PRIMARY KEY'        => 'id_fbiblio,id_objet,objet',
// 			'KEY id_fbiblio'     => 'id_fbiblio',
// 		]
// 	];

// 	return $tables;
// }
