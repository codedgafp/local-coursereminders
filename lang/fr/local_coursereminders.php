<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French strings for the course reminders plugin.
 *
 * @package    local_coursereminders
 * @copyright  2026 Enovation Solutions
 * @author     Fabien Dallet <fabien.dallet@enovationsolutions.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action:delete'] = 'Supprimer';
$string['action:disable'] = 'Désactiver';
$string['action:duplicate'] = 'Dupliquer';
$string['action:edit'] = 'Modifier';
$string['action:enable'] = 'Activer';
$string['action:reminderlog'] = 'Voir le journal des relances';
$string['action:sendmessage'] = 'Envoyer un message';
$string['action:unenrol'] = 'Désinscrire du cours';
$string['actionsforuser'] = 'Actions pour {$a}';
$string['addreminder'] = 'Ajouter une relance';
$string['allgroups'] = 'Tous les inscrits';
$string['backtomanage'] = 'Retour à la gestion des relances';
$string['badge:inactive'] = 'Inactif';
$string['bulkactionlabel'] = 'Pour les relances sélectionnées';
$string['col:actions'] = 'Actions';
$string['col:count'] = 'Nombre de relances';
$string['col:delay'] = 'Délai';
$string['col:group'] = 'Groupe concerné';
$string['col:lastsent'] = 'Dernière relance';
$string['col:status'] = 'Statut';
$string['col:type'] = 'Type de relance';
$string['col:user'] = 'Utilisateurs';
$string['confirmdelete'] = 'Êtes-vous sûr de vouloir supprimer cette relance ? Les relances déjà envoyées seront conservées dans l\'historique.';
$string['confirmdeletebulk'] = 'Êtes-vous sûr de vouloir supprimer les {$a} relances sélectionnées ? Les relances déjà envoyées seront conservées dans l\'historique.';
$string['confirmunenrol'] = 'Voulez-vous vraiment désinscrire {$a} de ce cours ?';
$string['coursereminders:manage'] = 'Gérer les relances de cours';
$string['coursereminders:send'] = 'Envoyer une relance individuelle';
$string['coursereminders:viewhistory'] = 'Consulter l\'historique des relances';
$string['default:body_inactivity'] = '<p>Bonjour [prenom],</p><p>Il semblerait que vous ne vous soyez pas connecté au cours [nom_cours] depuis [délai]. Pour reprendre votre formation, veuillez cliquer sur le lien suivant : [lien_cours]</p><p>Bien cordialement,</p>';
$string['default:body_postenrol'] = '<p>Bonjour [prenom],</p><p>Cela fait [délai] que vous avez été inscrit au cours [nom_cours]. Il semblerait que vous ne soyez pas encore connecté pour suivre la formation. Pour accéder au cours, veuillez cliquer sur le lien suivant : [lien_cours]</p><p>Bien cordialement,</p>';
$string['default:body_precourseend'] = '<p>Bonjour [prenom],</p><p>Le cours [nom_cours] se termine dans [délai] et vous ne l\'avez pas encore terminé. Pour accéder au cours, veuillez cliquer sur le lien suivant : [lien_cours]</p><p>Bien cordialement,</p>';
$string['default:subject_inactivity'] = 'Relance [nom_cours]';
$string['default:subject_postenrol'] = 'Relance [nom_cours]';
$string['default:subject_precourseend'] = 'Relance : le cours [nom_cours] se termine bientôt';
$string['delaydays'] = '{$a} jour(s)';
$string['delayweeks'] = '{$a} semaine(s)';
$string['export:date'] = 'Date';
$string['export:label'] = 'Exporter les relances envoyées au format';
$string['export:reminder'] = 'Relance';
$string['export:type'] = 'Type de relance';
$string['error:delaypositive'] = 'Le délai doit être d\'au moins 1 jour.';
$string['error:maxcountpositive'] = 'Le nombre maximum de relances doit être d\'au moins 1.';
$string['error:notarget'] = 'Sélectionnez au moins une méthode d\'inscription.';
$string['error:thresholdnonneg'] = 'Le seuil d\'alerte ne peut pas être négatif.';
$string['errorcannotunenrol'] = 'Vous ne pouvez pas désinscrire cet utilisateur de ce cours.';
$string['errornocompletionenable'] = 'Les relances ne peuvent pas être activées car le suivi d\'achèvement n\'est pas configuré sur ce cours.';
$string['errornoenddateenable'] = 'Une relance avant la fin du cours ne peut pas être activée tant qu\'aucune date de fin n\'est définie sur ce cours.';
$string['errorrulenotfound'] = 'La relance est introuvable.';
$string['field:alertthreshold'] = 'Seuil du badge d\'alerte';
$string['field:body'] = 'Personnaliser le message';
$string['field:delay'] = 'Délai';
$string['field:delete'] = 'Supprimer';
$string['field:delete_label'] = 'Supprimer cette relance';
$string['field:enabled'] = 'Activée';
$string['field:enabled_label'] = 'Activer cette relance';
$string['field:group'] = 'Sélectionner le groupe cible';
$string['field:maxcount'] = 'Nombre maximum de relances';
$string['field:subject'] = 'Objet du message';
$string['field:summary'] = 'Récapitulatif';
$string['field:target'] = 'Cible';
$string['field:type'] = 'Type de relance';
$string['formheading'] = 'Relance';
$string['groupnote'] = 'Le groupe cible ne peut être sélectionné que lorsque le cours utilise des groupes séparés. Dans tout autre mode de groupe, la relance s\'applique à tous les inscrits.';
$string['invalidaction'] = 'Action invalide.';
$string['managepageheading'] = 'Gestion des relances';
$string['managetablecaption'] = 'Liste des relances configurées sur ce cours';
$string['messageprovider:reminder'] = 'Relances de cours';
$string['nobulkaction'] = 'Veuillez choisir une action à appliquer aux relances sélectionnées.';
$string['nobulkselection'] = 'Veuillez sélectionner au moins une relance.';
$string['norules'] = 'Aucune relance n\'a encore été configurée sur ce cours.';
$string['notif:deleted'] = 'Relances supprimées.';
$string['notif:disabled'] = 'Relances désactivées.';
$string['notif:duplicated'] = 'Relance dupliquée.';
$string['notif:enabled'] = 'Relances activées.';
$string['notif:saved'] = 'Relance enregistrée.';
$string['notif:unenrolled'] = 'Utilisateur désinscrit.';
$string['placeholder:courseenddate'] = '[date_fin_cours]';
$string['placeholder:coursename'] = '[nom_cours]';
$string['placeholder:courseurl'] = '[lien_cours]';
$string['placeholder:delay'] = '[délai]';
$string['placeholder:enroldate'] = '[date_inscription]';
$string['placeholder:firstname'] = '[prenom]';
$string['placeholder:lastname'] = '[nom]';
$string['placeholder:weeks'] = '[nombre_semaines]';
$string['placeholdershelp'] = 'Champs disponibles : [prenom], [nom], [délai], [nom_cours], [lien_cours], [date_inscription], [date_fin_cours].';
$string['pluginname'] = 'Relances de cours';
$string['privacy:metadata:core_message'] = 'Les relances sont envoyées aux apprenants via le système de messagerie.';
$string['privacy:metadata:rule'] = 'Règles de relance configurées sur les cours.';
$string['privacy:metadata:rule:usermodified'] = 'L\'identifiant de l\'utilisateur ayant modifié la règle de relance en dernier.';
$string['privacy:metadata:sent'] = 'Journal des relances envoyées à chaque utilisateur.';
$string['privacy:metadata:sent:courseid'] = 'L\'identifiant du cours concerné par la relance.';
$string['privacy:metadata:sent:occurrence'] = 'Le rang de la relance dans la boucle de la règle.';
$string['privacy:metadata:sent:ruleid'] = 'L\'identifiant de la règle de relance à l\'origine du message.';
$string['privacy:metadata:sent:timesent'] = 'La date d\'envoi de la relance.';
$string['privacy:metadata:sent:type'] = 'Le type de déclencheur de la relance.';
$string['privacy:metadata:sent:userid'] = 'L\'identifiant de l\'utilisateur destinataire de la relance.';
$string['reminderlog:nosent'] = 'Aucune relance n\'a encore été envoyée à cet utilisateur.';
$string['reminderlog:noupcoming'] = 'Aucune relance à venir n\'est actuellement prévue pour cet utilisateur.';
$string['reminderlog:sentheading'] = 'Relances envoyées';
$string['reminderlog:title'] = 'Journal des relances de {$a}';
$string['reminderlog:upcomingheading'] = 'Relances à venir';
$string['rowactions'] = 'Actions pour cette relance';
$string['selectallreminders'] = 'Sélectionner toutes les relances';
$string['selectreminder'] = 'Sélectionner cette relance';
$string['sender:noreply'] = 'Utilisateur « ne pas répondre »';
$string['sender:support'] = 'Utilisateur de support';
$string['sender:teacher'] = 'Un enseignant du cours';
$string['sentlist:latestsend'] = 'Dernière relance envoyée sur ce cours : {$a}';
$string['sentlist:nousers'] = 'Aucun participant n\'est actuellement inscrit à ce cours.';
$string['sentlist:title'] = 'Liste des relances';
$string['sentlisttablecaption'] = 'Liste des participants inscrits et des relances reçues';
$string['settings:batchsending'] = 'Envoyer les relances par lots en arrière-plan';
$string['settings:batchsending_desc'] = 'Lorsque ce réglage est activé, la tâche planifiée n\'envoie plus les relances elle-même : elle les place dans des lots (tâches ad hoc) que le cron traite au fil de ses exécutions suivantes. Chaque relance est revérifiée au moment de l\'exécution de son lot, si bien qu\'un apprenant dont la situation a changé entre-temps (cours terminé, désinscription) est ignoré. Recommandé sur les sites comptant un grand nombre d\'utilisateurs.';
$string['settings:batchsize'] = 'Taille des lots';
$string['settings:batchsize_desc'] = 'Nombre maximum de relances par lot. Utilisé uniquement lorsque l\'envoi par lots est activé.';
$string['settings:defaultalertthreshold'] = 'Seuil d\'alerte par défaut';
$string['settings:defaultalertthreshold_desc'] = 'Nombre de relances par défaut au-delà duquel le badge d\'alerte « inactivité » est affiché à côté du nombre de relances d\'un apprenant. Les concepteurs de cours peuvent modifier cette valeur pour chaque règle.';
$string['settings:defaultbody'] = 'Corps du message par défaut';
$string['settings:defaultbody_desc'] = 'Valeur de départ du corps du message lorsqu\'un concepteur de cours crée une relance de ce type. Laissez vide pour utiliser le texte standard traduit dans la langue de chaque concepteur de cours. Champs disponibles : [prenom], [nom], [délai], [nom_cours], [lien_cours], [date_inscription], [date_fin_cours].';
$string['settings:defaultdelay'] = 'Délai par défaut';
$string['settings:defaultdelay_desc'] = 'Valeur de départ du délai, en jours ou en semaines, lorsqu\'un concepteur de cours crée une relance de ce type.';
$string['settings:defaultmaxcount'] = 'Nombre maximum de relances par défaut';
$string['settings:defaultmaxcount_desc'] = 'Valeur de départ pour le nombre maximum de relances qu\'une règle peut envoyer à un apprenant.';
$string['settings:defaultsender'] = 'Expéditeur par défaut';
$string['settings:defaultsender_desc'] = 'Identité utilisée par défaut comme expéditeur des messages de relance. Avec « Un enseignant du cours », les messages sont envoyés au nom de la personne qui a créé la règle tant qu\'elle enseigne encore dans le cours, sinon au nom du premier enseignant trouvé dans le cours, et à défaut au nom de l\'utilisateur « ne pas répondre » lorsque le cours n\'a aucun enseignant.';
$string['settings:defaultsubject'] = 'Objet par défaut';
$string['settings:defaultsubject_desc'] = 'Valeur de départ de l\'objet du message lorsqu\'un concepteur de cours crée une relance de ce type. Laissez vide pour utiliser le texte standard traduit dans la langue de chaque concepteur de cours.';
$string['settings:generalheading'] = 'Valeurs par défaut générales';
$string['settings:generalheading_desc'] = 'Ces valeurs servent de point de départ lorsqu\'un concepteur de cours crée une nouvelle relance.';
$string['settings:sendingheading'] = 'Envoi';
$string['settings:sendingheading_desc'] = 'Ces réglages contrôlent la manière dont la tâche planifiée expédie les relances à envoyer.';
$string['status:disabled'] = 'Désactivée';
$string['status:enabled'] = 'Activée';
$string['summary:inactivity'] = 'Une relance sera envoyée après chaque période de {delay} d\'inactivité, jusqu\'à {count} fois, jusqu\'à ce que l\'apprenant se reconnecte ou que le cours se termine.';
$string['summary:postenrol'] = 'Une relance sera envoyée {delay} après l\'inscription si l\'apprenant ne s\'est pas connecté au cours.';
$string['summary:precourseend'] = 'Une relance sera envoyée {delay} avant la date de fin du cours si le cours n\'est pas encore terminé.';
$string['target:all'] = 'Tous les inscrits';
$string['target:manual'] = 'Inscrits par inscription manuelle';
$string['target:other'] = 'Inscrits par une autre méthode';
$string['target:self'] = 'Inscrits par auto-inscription';
$string['task:sendreminderbatch'] = 'Envoyer un lot de relances de cours';
$string['task:sendreminders'] = 'Envoyer les relances de cours';
$string['type:inactivity'] = 'Inactivité';
$string['type:postenrol'] = 'Après inscription';
$string['type:precourseend'] = 'Avant la fin du cours';
$string['warning:nocompletion'] = 'Le suivi d\'achèvement n\'est pas configuré sur ce cours. Les relances ne peuvent pas être activées et aucune ne sera envoyée tant que le suivi d\'achèvement n\'est pas activé dans les paramètres du cours.';
$string['warning:noenddate'] = 'Ce cours n\'a pas de date de fin. Une relance avant la fin du cours ne peut pas s\'exécuter, ni être activée, tant qu\'une date de fin n\'est pas définie dans les paramètres du cours.';
