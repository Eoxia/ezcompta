# [EzCompta] [23.0.0] - Rapport entrées/sorties sur données bancaires - Première version publiée

Description : **Première version publiée** du module. Elle apporte le rapport **entrées / sorties** construit sur les données bancaires réelles, avec l'explication de l'origine des chiffres et un détail par compte, le début de l'**import bancaire**, et la prise en compte du mois de début d'exercice de la société.

**Le module demande Dolibarr 23 au minimum et 24 au maximum.**

## Nouvelles fonctionnalités et innovations

### Rapport entrées / sorties

* Le rapport s'appuie sur les **données bancaires réelles**.
* Chaque chiffre est **expliqué** : d'où il vient, et son détail par compte.
* Le **mois de début d'exercice** de la société est respecté.

### Import bancaire

* Première étape de la fonctionnalité d'import des relevés.

### Navigation

* Réorganisation des entrées de menu.

## Améliorations & corrections

* Plus de génération de PDF à l'enregistrement d'un relevé bancaire.
* Correction de l'affichage HTML du treeview des comptes.
* Les deux endpoints ajax chargent l'environnement Dolibarr avec **deux tentatives** — une pour le module à la racine, une pour le module dans `custom` : le contrôle de paquet du Dolistore refusait le zip, et l'installation à la racine cassait ces appels.
* `accountingaccount_activate.php` répondait `success:true` sans avoir rien fait lorsque l'action était inconnue ou les droits absents : le résultat du basculement ne partage plus sa variable avec celui du chargement de l'environnement.

## Note sur la numérotation

Le module n'avait jamais été tagué et son descripteur déclarait `0.1.0`. Il rejoint la convention du parc Evarisk, où le numéro majeur correspond à la version majeure de Dolibarr visée — d'où cette première publication en `23.0.0`.
