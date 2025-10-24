# YoCo WooCommerce Loyalty

Een uitgebreide loyalty plugin voor WooCommerce met puntensysteem, beloningen en automatische GitHub updates. Ontwikkeld door Your Coding.

## 🚀 Features

### Huidige Versie (1.0.0)
- ✅ Basis plugin structuur
- ✅ GitHub automatische updates
- ✅ WooCommerce dependency check
- ✅ Admin interface met YoCo branding
- ✅ Database tabellen setup
- ✅ Plugin activatie/deactivatie
- ✅ Instellingen voor punten configuratie

### Geplande Features
- 🎯 Uitgebreid puntensysteem per bestelling
- 🎁 Flexibele beloningen en vouchers
- 📊 Klant loyalty dashboard
- 📈 Detailleerde loyalty analytics
- 🔄 Automatische acties en triggers
- 📧 E-mail notificaties voor klanten
- 🏆 Tier/level systeem
- 🎯 Referral programma

## 👨‍💻 Over Your Coding

Deze plugin is ontwikkeld door **Your Coding** - Sebastiaan Kalkman, een gespecialiseerde WordPress en WooCommerce ontwikkelaar.

- 🌐 **Website**: [www.yourcoding.nl](https://www.yourcoding.nl)
- 📧 **Email**: info@yourcoding.nl
- 🎯 **Specialisatie**: Custom WordPress & WooCommerce ontwikkeling
- 🔧 **Services**: Plugin development, theme customization, WooCommerce uitbreidingen

## 📋 Vereisten

- WordPress 5.0 of hoger
- WooCommerce 5.0 of hoger
- PHP 7.4 of hoger
- MySQL 5.6 of hoger

## 🛠 Installatie

### Methode 1: GitHub Releases (Aanbevolen)
1. Download de nieuwste release van: `https://github.com/YourCoding/yoco-woocommerce-loyalty/releases`
2. Upload het ZIP bestand via WordPress Admin → Plugins → Plugin toevoegen → Plugin uploaden
3. Activeer de plugin

### Methode 2: Git Clone
```bash
cd wp-content/plugins/
git clone https://github.com/YourCoding/yoco-woocommerce-loyalty.git
```

### Methode 3: Direct Download
1. Download of clone deze repository
2. Upload de plugin folder naar `wp-content/plugins/`
3. Activeer de plugin via WordPress Admin

## ⚙️ GitHub Update Setup

### 1. Plugin Configuratie
De plugin is al geconfigureerd met de juiste GitHub repository details:

```php
// Repository configuratie (al ingesteld):
'YourCoding' => 'GitHub organization/username'
'yoco-woocommerce-loyalty' => 'Repository naam'
```

### 2. GitHub Repository Setup

#### Repository Structuur
```
yoco-woocommerce-loyalty/
├── woocommerce-loyalty-plugin.php (hoofdbestand)
├── includes/
│   └── class-github-updater.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── README.md
├── CHANGELOG.md
└── LICENSE
```

#### Release Tags
Voor automatische updates moet je versie tags maken:
```bash
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

#### Private Repository (Optioneel)
Voor private repositories, voeg een GitHub Personal Access Token toe:

```php
// In de plugin file, in de init_github_updater() methode
$github_token = 'ghp_jouwtoken'; // Optioneel voor private repos
$this->github_updater = new YoCo_Loyalty_GitHub_Updater(
    YOCO_LOYALTY_PLUGIN_FILE,
    'YourCoding',
    'yoco-woocommerce-loyalty',
    'main',
    $github_token // Voeg token toe
);
```

### 3. WordPress Plugin Header
De plugin header is al correct geconfigureerd:

```php
/**
 * Plugin Name: YoCo WooCommerce Loyalty
 * Update URI: https://github.com/YourCoding/yoco-woocommerce-loyalty
 * GitHub Plugin URI: YourCoding/yoco-woocommerce-loyalty
 * GitHub Branch: main
 */
```

## 🔄 Update Proces

### Automatische Updates
- Plugin controleert elke 12 uur op nieuwe versies
- Updates verschijnen in WordPress Admin → Dashboard → Updates
- Klik op "Nu bijwerken" om de nieuwste versie te installeren

### Handmatige Update Check
1. Ga naar WordPress Admin → YoCo Loyalty → Instellingen
2. Klik op "Check voor Updates"
3. Als er een update beschikbaar is, wordt deze getoond

### Update Cache Wissen
Als updates niet verschijnen:
1. Ga naar de plugin pagina
2. Klik op "Clear Cache" in de plugin row meta
3. Of deactiveer/activeer de plugin opnieuw

## 📁 Bestandsstructuur

```
yoco-woocommerce-loyalty/
├── woocommerce-loyalty-plugin.php    # Hoofdbestand
├── includes/                         # PHP klassen
│   ├── class-github-updater.php     # GitHub update functionaliteit
│   ├── admin/                       # Admin klassen (toekomstig)
│   └── frontend/                    # Frontend klassen (toekomstig)
├── assets/                          # CSS/JS bestanden
│   ├── css/
│   │   └── admin.css               # Admin styling
│   └── js/
│       └── admin.js                # Admin JavaScript
├── languages/                       # Vertalingen (toekomstig)
├── templates/                       # Template bestanden (toekomstig)
└── includes/sql/                    # Database scripts (toekomstig)
```

## 🛡️ Beveiliging

- Plugin controleert op directe toegang
- Alle AJAX calls gebruiken nonces
- Database queries zijn prepared statements
- GitHub token wordt veilig opgeslagen

## 📊 Database

### Tabellen
De plugin maakt de volgende tabellen aan:

#### `wp_yoco_loyalty_points`
```sql
CREATE TABLE wp_yoco_loyalty_points (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    points int(11) NOT NULL DEFAULT 0,
    total_earned int(11) NOT NULL DEFAULT 0,
    total_spent int(11) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id)
);
```

## 🐛 Debug & Troubleshooting

### Debug Modus
Voeg toe aan `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Veelvoorkomende Problemen

#### Updates Werken Niet
1. Controleer GitHub repository URL
2. Verificeer versie tags in repository
3. Check WordPress error logs
4. Wis plugin cache

#### WooCommerce Niet Gedetecteerd
1. Controleer of WooCommerce actief is
2. Update WooCommerce naar nieuwste versie
3. Deactiveer/activeer beide plugins

#### GitHub API Limiet
- Public repositories: 60 requests per uur
- Met token: 5000 requests per uur
- Cache voorkomt te veel requests

## 📝 Development

### Nieuwe Features Toevoegen
1. Maak nieuwe branch: `git checkout -b feature/nieuwe-feature`
2. Ontwikkel feature
3. Test thoroughly
4. Merge naar main branch
5. Maak nieuwe release tag

### Code Standards
- Volg WordPress Coding Standards
- Gebruik PHP_CodeSniffer voor validatie
- Documenteer alle functies
- Voeg unit tests toe waar mogelijk

## 📞 Support

Voor vragen, support en maatwerk ontwikkeling:
- 🌐 **Website**: [www.yourcoding.nl](https://www.yourcoding.nl)
- 📧 **Email**: info@yourcoding.nl
- 🔧 **GitHub Issues**: `https://github.com/YourCoding/yoco-woocommerce-loyalty/issues`
- 👨‍💻 **Ontwikkelaar**: Sebastiaan Kalkman - Your Coding

### Maatwerk Ontwikkeling
Your Coding biedt ook maatwerk WordPress en WooCommerce ontwikkeling:
- Custom plugin development
- WooCommerce uitbreidingen
- Theme customization
- Performance optimalisatie
- Onderhoud en support

## 📄 Licentie

GPL v2 or later - zie LICENSE bestand voor details.

## 🔄 Changelog

### 1.0.0 (2024-10-24)
- Eerste release
- Basis plugin structuur
- GitHub update functionaliteit
- Admin interface
- WooCommerce integratie check

---

**Tip:** Bookmark deze README voor toekomstige referentie tijdens development!