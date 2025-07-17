# SJ4WEB - Payplug Report Tracker

Module PrestaShop pour récupérer automatiquement les frais Payplug liés aux commandes et les enregistrer dans la base de données.

## 🔧 Fonctionnalités principales

- Génère automatiquement un rapport comptable Payplug lors de la confirmation de paiement.
- Récupère et traite le fichier CSV du rapport.
- Extrait les frais de transaction (`total_fees_excl._vat_(€)`) et les stocke dans la table `order_fees`.
- Interface back-office pour visualiser les rapports générés.
- Intégration transparente avec le module natif `payplug`.

## 🧩 Dépendances

- Module `payplug` (officiel) **obligatoire et actif**.
- Bibliothèque SDK Payplug chargée depuis `modules/payplug/vendor/autoload.php`.

## 📦 Installation

1. Copier le dossier `sj4web_payplugreport/` dans `/modules/`.
2. Installer le module depuis le back-office de PrestaShop.
3. Vérifier que le module `payplug` est bien installé et actif.

Le module créera automatiquement la table suivante :

```sql
CREATE TABLE ps_order_payplug_reports (
  id_order INT(11) NOT NULL,
  id_report VARCHAR(50) NOT NULL,
  report_treated TINYINT(1) NOT NULL DEFAULT 0,
  date_add DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_order)
);
