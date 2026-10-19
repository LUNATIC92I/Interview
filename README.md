# InterviewPro

Plateforme web bilingue (français / anglais) de simulation d'entretiens d'embauche : catalogue de 74 métiers répartis en 7 catégories, banque de 267 questions rédigées dans les deux langues, 15 scénarios, moteur de simulation en 5 modes, évaluation structurée sur 100, tableau de bord de progression, rapport PDF et back office complet.

Socle technique imposé : **PHP 8.2+ (MVC sans framework), MySQL 8 / MariaDB 10.6+ via PDO, HTML5, CSS3 et JavaScript natif**. Aucune dépendance Composer ni npm n'est nécessaire à l'exécution : l'application tourne sur un hébergement PHP/MySQL classique.

---

## Sommaire

1. [Fonctionnalités](#fonctionnalités)
2. [Structure du projet](#structure-du-projet)
3. [Installation locale](#installation-locale)
4. [Configuration (.env)](#configuration-env)
5. [Déploiement en production](#déploiement-en-production)
6. [Compte administrateur initial](#compte-administrateur-initial)
7. [Moteur d'évaluation](#moteur-dévaluation)
8. [Intégration optionnelle d'un modèle linguistique](#intégration-optionnelle-dun-modèle-linguistique)
9. [Sécurité](#sécurité)
10. [Tests](#tests)
11. [Exploitation : sauvegardes, maintenance, migrations](#exploitation)
12. [Limites connues](#limites-connues)

---

## Fonctionnalités

**Espace public**
- Page d'accueil (accroche FR/EN, composition visuelle HTML/CSS d'une simulation, fonctionnalités, catégories, étapes, avantages, FAQ, appel à l'action). Aucune statistique, témoignage ni certification inventés.
- Catalogue des métiers avec recherche, filtre par catégorie et pagination serveur ; fiche métier (responsabilités, compétences recherchées, niveaux, exemples de questions, scénarios disponibles, métiers proches).
- Les 51 métiers du cahier des charges, complétés par 23 métiers courants : technicien support informatique, développeur mobile, testeur QA, designer UX/UI, ingénieur réseaux, ingénieur machine learning, responsable conformité, gestionnaire de paie, Scrum Master, responsable qualité, chargé de formation, coordinateur de projets humanitaires, infirmier (entretien non clinique), technicien de maintenance industrielle, conducteur de travaux, responsable supply chain, architecte, traducteur interprète, et une catégorie « Commerce, accueil et services » (conseiller de vente, conseiller relation client, réceptionniste en hôtellerie, agent immobilier, assistant de direction). Chaque métier ajouté dispose de sa fiche et de 3 questions bilingues propres, en plus des questions de sa catégorie et des questions générales.
- Sur une installation existante, `php bin/console seed` ajoute les nouveaux métiers et questions sans toucher aux données déjà présentes.
- Pages Simulations (types et modes), Fonctionnalités, Conseils (contenus pédagogiques), Politique de confidentialité.
- Sélecteur **FR | EN** toujours visible ; le changement de langue conserve la session, les brouillons et la progression.

**Comptes**
- Inscription, connexion, déconnexion, confirmation d'adresse (si un envoi de courriels est configuré), mot de passe oublié avec jeton à usage unique valable 60 min, profil, préférences (langue, mode par défaut, réduction des animations), changement de mot de passe, historique des connexions (IP tronquée), suppression définitive du compte et de toutes les données associées.

**Animations**
- Système de mouvement unique dans `app.css` (jetons `--dur*`, `--ease*`) : entrée échelonnée du hero, apparition au défilement avec variantes (`data-anim="left|right|scale|fade"`) et décalage automatique entre éléments voisins, survols des cartes, boutons et liens, ombre de l'en-tête au défilement, barre de lecture sur les articles (animation pilotée par le défilement), transitions entre pages (View Transitions, ignorées par les navigateurs non compatibles).
- Le back office et l'écran d'entretien restent volontairement sobres. Tout est désactivé avec `prefers-reduced-motion` ou la préférence « réduire les animations » du compte, et rien n'est animé à l'impression.

**Moteur de simulation**
- Paramètres : métier, niveau (étudiant, débutant, intermédiaire, senior, manager), 14 types d'entretien, langue, difficulté (5 niveaux), durée, nombre de questions, mode, scénario facultatif.
- Modes : **Entraînement** (conseil + retour + réponse indicative après chaque question), **Réaliste** (retour à la fin), **Chronométré** (temps par question = durée / nombre de questions), **Intensif** (difficulté ≥ 4, pas de saut, relance systématique, temps réduit), **Personnalisé** (saut, retour, pause, chronomètre, relances, retour immédiat au choix).
- Sélection automatique : répartition par type de question selon le type d'entretien, priorité aux questions du métier puis de sa catégorie puis générales, respect du niveau, proximité de la difficulté, questions disponibles dans la langue choisie et les moins vues par le candidat.
- Une question à la fois, sauvegarde automatique (serveur + copie locale `localStorage`), reprise après rechargement ou coupure réseau (renvoi au retour en ligne, `sendBeacon` à la fermeture), pause, retour en arrière selon le mode, navigation, confirmation avant la fin, durée de réponse enregistrée côté serveur.
- **Relances conditionnelles prédéfinies** en base (`question_followups`) : mots-clés présents, réponse trop courte, absence de résultat, absence de rôle personnel, ou toujours. Elles sont présentées comme des questions prédéfinies, jamais comme une conversation avec un recruteur réel.
- Signalement d'une question par le candidat (modération dans le back office).

**Évaluation et rapports** : voir [Moteur d'évaluation](#moteur-dévaluation). Rapport post entretien (score global, par critère, par compétence, points forts, axes, recommandations prioritaires, réponses commentées, détail de notation, réponse indicative), impression optimisée et **export PDF** natif, comparaison de deux tentatives, relance de la même simulation.

**Tableau de bord** : entretiens réalisés et terminés, score moyen et meilleur score, courbe d'évolution (SVG accessible + tableau de données), résultats par catégorie et par type, compétences les mieux maîtrisées / à améliorer, métiers travaillés, simulations sauvegardées à reprendre, dernières simulations, objectifs de progression, historique filtrable avec sélection de deux tentatives à comparer. Rappel permanent : un score d'entraînement n'est pas une probabilité d'être recruté.

**Back office** (`/admin`, rôle administrateur) : tableau de bord agrégé, utilisateurs (recherche, filtres, suspension, rôles), catégories, métiers, questions (CRUD bilingue complet, éléments attendus, erreurs fréquentes, exemples, compétences, relances), **import/export CSV** validé, scénarios (questions ordonnées), grilles de notation (poids = 100), niveaux de difficulté, contenus pédagogiques, traductions (surcharge de n'importe quelle chaîne d'interface + suivi des contenus non traduits), signalements, paramètres, journal d'audit. Aucune réponse privée de candidat n'y est affichée.

## Structure du projet

```
public/                 Seul dossier exposé au web : index.php (point d'entrée unique), assets/, .htaccess
  assets/css/app.css    Charte turquoise, composants, animations, impression, prefers-reduced-motion
  assets/js/            app.js (commun), interview.js (moteur côté client), admin.js
app/
  bootstrap.php         Autoload (PSR-4 sans Composer), .env, configuration, gestion d'erreurs
  Core/                 App (noyau HTTP), Router, Request, Response, View, Database (PDO), Session, Csrf,
                        Translator, Validator, Config, Env, Logger, ErrorHandler
  Controllers/          Pages publiques, Auth, Dashboard, Interview, Report, Account, Install
  Controllers/Admin/    Contrôleurs du back office
  Middleware/           auth, guest, admin, verified
  Models/               Accès aux données (Profession, Question, Scenario, Rubric, Skill, Domain…)
  Services/             InterviewEngine, EvaluationService, Evaluation/ (moteur de règles, LLM),
                        ReportService, ReportPdf, PdfDocument, QuestionService, QuestionCsv,
                        UserService, Auth, Mailer, SmtpClient, RateLimiter, AuditLogger, Migrator…
  Views/                Gabarits PHP (layouts, partials, pages, auth, interview, reports, admin…)
  Helpers/              functions.php (e(), t(), url(), icon()…), Paginator, Charts (SVG)
config/                 app, database, security, mail, ai
database/migrations/    001_initial_schema.sql, 002_engagement_and_moderation.sql (versionnées)
database/seeders/       DatabaseSeeder (référentiels + contenu), DemoSeeder, data/*.php (contenu bilingue)
lang/fr.php, lang/en.php  Textes d'interface centralisés (clés identiques, vérifié par les tests)
routes/web.php          Déclaration des routes et middlewares
storage/logs, reports, cache   Journaux (JSON lines), fichiers temporaires — interdits d'accès web
tests/                  run.php (lanceur), Unit/, Http/, Support/, browser/ (Playwright)
bin/console             migrate, seed, demo:seed, admin:create, key:generate, cleanup
server.php              Routeur pour le serveur intégré PHP (développement uniquement)
```

## Installation locale

Prérequis : PHP ≥ 8.2 avec `pdo_mysql`, `mbstring`, `json` (recommandés : `intl`, `curl`, `fileinfo`), MySQL 8 ou MariaDB ≥ 10.6.

```bash
# 1. Base de données (exemple)
mysql -u root -p -e "CREATE DATABASE interviewpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'interviewpro'@'localhost' IDENTIFIED BY 'mot-de-passe-solide';
  GRANT ALL ON interviewpro.* TO 'interviewpro'@'localhost';"

# 2. Configuration
cp .env.example .env
php bin/console key:generate        # copier la valeur dans APP_KEY
# renseigner DB_*, puis pour le développement : APP_ENV=local, APP_DEBUG=true, MAIL_DRIVER=log

# 3. Schéma et contenu
php bin/console migrate             # applique les migrations en attente
php bin/console seed                # référentiels, 74 métiers, 267 questions FR/EN, 15 scénarios (idempotent)
php bin/console admin:create        # e-mail, nom, mot de passe saisi sans écho
php bin/console demo:seed           # facultatif : candidat de démonstration (mot de passe affiché une fois)

# 4. Lancer
php -S 127.0.0.1:8000 -t public server.php
```

Ouvrir http://127.0.0.1:8000. Avec `MAIL_DRIVER=log`, les courriels (confirmation, réinitialisation) sont écrits dans `storage/logs/mail.log`.

`composer install` n'est pas requis ; `composer.json` déclare simplement l'autoload et des scripts (`composer test`, `composer migrate`, `composer seed`).

## Configuration (.env)

| Variable | Rôle |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `production` et `false` en production (aucune trace technique affichée) |
| `APP_URL` | URL publique, utilisée dans les liens des courriels |
| `APP_LOCALE` | Langue par défaut (`fr` ou `en`) ; sinon `Accept-Language` |
| `APP_KEY` | Clé aléatoire (`php bin/console key:generate`) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SOCKET` | Connexion MySQL |
| `SESSION_IDLE_MINUTES`, `SESSION_ABSOLUTE_HOURS` | Expiration de session (30 min d'inactivité, 12 h maximum par défaut) |
| `SESSION_SECURE_COOKIE` | `auto` (Secure si HTTPS détecté), `true` recommandé en production |
| `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_MINUTES` | Limitation des tentatives de connexion (5 / 15 min par compte, ×4 par IP) |
| `MAIL_DRIVER` | `none`, `log`, `mail` (fonction PHP) ou `smtp` (+ `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM`) |
| `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL`, `AI_EFFORT`, `AI_BASE_URL`, `AI_TIMEOUT_SECONDS`, `AI_MAX_CALLS_PER_USER_PER_DAY` | Analyse optionnelle par modèle linguistique (vide = désactivée) |
| `INSTALL_TOKEN` | Active l'installateur web `/install` tant qu'aucun administrateur n'existe |

Paramètres modifiables dans l'administration (table `app_settings`) : inscriptions ouvertes, confirmation d'adresse obligatoire, utilisation du modèle linguistique, simulations maximum par jour, nombre de questions par défaut, adresse de contact, message d'information affiché sur le site.

## Déploiement en production

1. Déposer le projet **hors** de la racine web si possible et faire pointer le document root sur `public/`.
2. `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `APP_URL=https://…`, un mot de passe MySQL dédié, un `MAIL_DRIVER` réel.
3. `php bin/console migrate && php bin/console seed && php bin/console admin:create`.
4. Droits : le serveur web doit pouvoir écrire dans `storage/` uniquement.
5. HTTPS obligatoire (l'en-tête HSTS est envoyé automatiquement en HTTPS ; le cookie de session prend alors le préfixe `__Host-`).
6. Tâche planifiée quotidienne : `php bin/console cleanup` (purge des jetons expirés et des compteurs de limitation).

**Apache** : `public/.htaccess` réécrit vers `index.php`. Les dossiers `storage/*` contiennent un `.htaccess` « deny ». Si l'hébergeur impose la racine du projet comme document root, le `.htaccess` racine redirige tout vers `public/` et bloque `app/`, `config/`, `database/`, `storage/`, `tests/`, `.env`…

**Nginx** (exemple) :

```nginx
server {
    listen 443 ssl http2;
    server_name interviewpro.example.org;
    root /var/www/interviewpro/public;
    index index.php;
    client_max_body_size 4m;
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\. { deny all; }
}
```

**Hébergement mutualisé sans SSH** : importer le projet, créer la base, renseigner `.env` avec un `INSTALL_TOKEN` long et aléatoire, ouvrir `/install` : l'installateur exécute les migrations, charge le contenu et crée le premier administrateur. Il se désactive dès qu'un administrateur existe ; supprimer ensuite `INSTALL_TOKEN`.

L'application fonctionne aussi dans un sous-dossier (`https://exemple.org/interviewpro/public/`) : toutes les URL sont générées avec le chemin de base détecté.

## Compte administrateur initial

Aucun mot de passe n'est livré dans le dépôt. Deux procédures sécurisées :
- `php bin/console admin:create [email]` : saisie masquée du mot de passe (ou variable `ADMIN_PASSWORD` pour l'automatisation), contrôle de robustesse, journalisation dans `audit_logs`. Si le compte existe déjà, il reçoit le rôle administrateur.
- Installateur web `/install`, protégé par `INSTALL_TOKEN` et disponible uniquement tant qu'il n'y a aucun administrateur.

## Moteur d'évaluation

Chaque question est rattachée à une **grille** (`evaluation_rubrics` / `rubric_criteria`) dont les poids totalisent 100 et sont modifiables dans l'administration. Grilles livrées :

| Grille | Critères (points) |
|---|---|
| Comportementale (STAR) | Pertinence 25, Clarté et structure 20, Qualité des actions 25, Résultats et preuves 20, Recul 10 |
| Technique | Exactitude 35, Raisonnement 25, Méthode 20, Communication 20 |
| Managériale | Leadership 25, Décision 20, Communication 20, Conflits et priorités 20, Résultats 15 |
| Motivation | Motivation argumentée 35, Pertinence 25, Structure 20, Communication 20 |
| Organisations internationales | Valeurs et intégrité 25, Pertinence 25, Actions personnelles 20, Résultats 15, Structure 15 |
| Mise en situation | Compréhension 25, Démarche 25, Raisonnement 25, Actions 15, Clarté 10 |
| Négociation | Préparation et arguments 35, Argumentation 25, Pertinence 20, Posture 20 |
| Générale | Pertinence 30, Structure 25, Communication 20, Lien avec le poste 15, Exemples 10 |

Le moteur de règles (`app/Services/Evaluation/RuleBasedEvaluator.php`) calcule pour chaque critère un ratio entre 0 et 1 à partir d'indices **observables dans le texte**, sans accent ni casse :
- couverture des **éléments attendus** propres à la question (chaque élément porte ses mots-clés FR/EN) ;
- structure : nombre de phrases, connecteurs, paragraphes ou énumérations, marqueurs STAR ;
- actions : verbes d'action à la première personne, part du « je » face au « nous » ;
- résultats : marqueurs de résultat et données chiffrées ; recul : enseignements tirés ;
- raisonnement (« parce que », « si », risques, compromis), méthode (étapes), leadership, éthique, motivation, négociation ;
- communication : longueur relative à la durée recommandée, lisibilité, remplissage ou répétitions.

Une réponse très courte est plafonnée (facteur de longueur). Score = Σ (poids × ratio). Le retour comprend : score, points forts, lacunes, compétences démontrées, éléments manquants, recommandations, conseil pour la prochaine tentative, réponse indicative, critères de correction, erreurs fréquentes, **niveau de confiance** (faible pour une réponse courte, moyen sinon ; jamais « élevé » pour ce moteur). Les retours sont stockés sous forme de clés de traduction : un rapport se lit dans les deux langues.

Engagements : l'évaluation ne porte jamais sur la personnalité, l'origine, le genre, la religion, l'âge ou toute caractéristique protégée ; elle ne prétend mesurer ni la véracité, ni le langage corporel, ni la confiance psychologique ; les réponses indicatives sont présentées comme un exemple parmi d'autres ; chaque rapport rappelle qu'un score n'est pas une probabilité d'être recruté et indique quel moteur l'a produit.

## Intégration optionnelle d'un modèle linguistique

Désactivée par défaut : sans clé, tout fonctionne avec le moteur de règles. Pour l'activer :

```dotenv
AI_PROVIDER=anthropic          # ou openai_compatible (+ AI_BASE_URL)
AI_API_KEY=...                 # stockée côté serveur uniquement
AI_MODEL=claude-opus-5-5
AI_EFFORT=medium
AI_TIMEOUT_SECONDS=45
AI_MAX_CALLS_PER_USER_PER_DAY=40
```

`LlmEvaluator` appelle l'API depuis PHP (cURL), avec une sortie JSON contrainte par schéma ; le modèle renvoie un ratio par critère et le score est recalculé avec les poids de la grille. Quota par utilisateur et par jour, délai d'expiration, gestion des refus et réponses invalides : en cas d'échec, l'évaluation retombe sur le moteur de règles et le rapport l'indique (« le service externe n'était pas disponible »). Aucune analyse n'est jamais inventée. Seuls la question, les éléments attendus et la réponse sont transmis (ni nom ni adresse). L'option peut être coupée à tout moment dans Administration > Paramètres.

## Sécurité

- Requêtes **préparées PDO** exclusivement (émulation désactivée) ; noms de tables/colonnes jamais issus de l'utilisateur ; échappement des jokers `LIKE`.
- Échappement systématique des sorties (`e()` = `htmlspecialchars` ENT_QUOTES/HTML5) ; JSON embarqué avec `JSON_HEX_*`.
- **CSRF** : jeton de session vérifié sur toute requête POST (champ `_csrf` ou en-tête `X-CSRF-Token` pour `fetch`).
- Mots de passe `password_hash` / `password_verify` (rehash automatique), politique de robustesse, jetons de réinitialisation et de confirmation aléatoires 256 bits stockés **hachés**, usage unique, expiration, réponse identique que le compte existe ou non.
- Sessions : `use_strict_mode`, cookies HttpOnly + SameSite=Lax + Secure en HTTPS (`__Host-`), régénération de l'identifiant à la connexion et au changement de mot de passe, expiration d'inactivité et absolue.
- Limitation de débit persistante (connexion par compte et par IP, inscription, réinitialisation, création de simulations, export PDF, import CSV, signalements, appels LLM).
- Autorisations : middlewares `auth` / `admin`, chaque simulation et rapport est chargé avec `user_id` (404 pour les ressources d'autrui, sans divulguer leur existence).
- En-têtes : Content-Security-Policy stricte (`script-src 'self'`, aucun script en ligne), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, HSTS en HTTPS, `Cache-Control: no-store` pour les pages authentifiées.
- Import CSV : extension, type MIME réel, taille, encodage UTF-8, en-tête exact, validation ligne par ligne, transaction tout-ou-rien ; export protégé contre l'injection de formules.
- Erreurs : journalisées dans `storage/logs` (JSON), jamais affichées en production ; page 503 dédiée si la base est indisponible.
- Journal d'audit des opérations sensibles (connexions, administration, exports, suppressions), IP tronquées.
- Données personnelles minimisées, suppression complète du compte (sans cascade implicite : suppression explicite et ordonnée), statistiques d'administration agrégées uniquement.
- Le schéma évite les suppressions en cascade sur les historiques (`RESTRICT`) ; un contenu déjà utilisé est désactivé plutôt que supprimé.

## Tests

```bash
php tests/run.php              # tests unitaires et HTTP (base dédiée interviewpro_test, serveur intégré)
php tests/run.php --browser    # + parcours navigateur Chromium (Playwright) sur mobile 375 px
php tests/run.php --filter=Security
```

Le lanceur recrée la base de test (`TEST_DB_DATABASE`, par défaut `interviewpro_test`, l'utilisateur MySQL doit y avoir les droits), applique migrations et contenu, démarre un serveur PHP sur un port libre et exécute les tests. Aucune dépendance externe (PHPUnit non requis). Le parcours navigateur nécessite Node.js et Playwright.

Couverture : inscription, connexion, déconnexion, régénération de session, limitation des tentatives, réinitialisation du mot de passe, confirmation d'adresse, suppression de compte ; séparation des données entre utilisateurs (pages, API, PDF, comparaison) ; changement de langue (pages FR/EN, conservation du brouillon) ; création de simulation, sélection des questions, règles des modes, enregistrement, reprise, pause, relances, expiration du chronomètre ; calcul des scores (moteur de règles et LLM simulé, y compris les pannes) ; rapport, export PDF (validité de la structure) ; permissions administratives ; CSRF ; injections SQL ; échappement XSS ; fichiers sensibles non servis ; redirection ouverte ; validation des imports CSV ; parité des traductions ; base indisponible (503) ; affichage mobile sans défilement horizontal et absence d'erreur JavaScript.

Résultat de la dernière exécution : **55 tests PHP réussis (466 assertions) et 16 vérifications navigateur réussies**.

## Exploitation

- **Migrations** : ajouter un fichier `database/migrations/NNN_description.sql` ; `php bin/console migrate` n'applique que les nouveaux fichiers (table `schema_migrations`). `migrate:status` affiche l'état.
- **Contenu** : `php bin/console seed` est idempotent (il n'écrase pas les modifications faites dans l'administration). Les nouveaux contenus s'ajoutent dans le back office ou par import CSV.
- **Sauvegardes** : sauvegarde quotidienne de la base, par exemple
  `mysqldump --single-transaction --routines interviewpro | gzip > /backups/interviewpro-$(date +%F).sql.gz`,
  conservation de 30 jours, copie hors site, test de restauration régulier. Sauvegarder aussi `.env` (dans un coffre) et `storage/logs` si nécessaire.
- **Journaux** : `storage/logs/app-AAAA-MM-JJ.log` (erreurs), `security-*.log` (CSRF, tentatives, accès refusés), `mail.log` (pilote `log`).

## Limites connues

- L'évaluation automatique repose sur des indices textuels : elle est **indicative**, aide à structurer les réponses mais ne remplace pas le jugement d'un recruteur. L'analyse par modèle linguistique, si elle est activée, reste également indicative.
- Les relances sont prédéfinies (une par question au maximum) : il ne s'agit pas d'un dialogue libre.
- Pas d'offre payante : le lien « Tarifs » n'est donc pas affiché.
- Le changement d'adresse électronique passe par l'administrateur.
- La police Inter est chargée depuis Google Fonts ; à défaut, la police système prend le relais.
- Les icônes sont un sous-ensemble de [Lucide](https://lucide.dev) (licence ISC) intégré en SVG.
