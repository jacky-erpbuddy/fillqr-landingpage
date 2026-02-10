# fillQR - Projekt-Dokumentation

> QR-Code-basiertes Mitgliedsantrags-System als SaaS Multi-Tenant Abo-Produkt.

---

## Quick Info

| Aspekt | Wert |
|--------|------|
| **Status** | ✅ Aktiv (Landingpage) / 🔨 Dev (App) |
| **Version** | v2.0.0 (Landingpage) / v0.1.2 (App) |
| **Stack** | PHP, MySQL, HTML/CSS/JS |
| **Server** | Variomedia (Shared Hosting) |
| **Domain** | fillqr.de |
| **Pricing** | 34,95 EUR/Monat + 99 EUR Design (inkl. MwSt) |
| **Zielgruppe** | B2B (Vereine, Restaurants) |
| **Git Repo** | jacky-erpbuddy/fillqr-landingpage (Landingpage) |

---

## Beschreibung

fillQR ermoeglicht Vereinen und Organisationen, Mitgliedsantraege ueber QR-Codes digital zu erfassen.
Mitglieder scannen einen QR-Code, fuellen ein Online-Formular aus, und der Verein verwaltet die Antraege ueber ein Admin-Backend.
Multi-Tenant-faehig: Jeder Verein hat seine eigene Subdomain und Konfiguration.

---

## Architektur

```
fillqr.de (Landingpage)
  ├── index.html          ← Produktseite, Pricing, CTA
  ├── datenschutz.html    ← DSGVO-konform
  ├── impressum.html      ← Rechtlich
  ├── nutzungsbedingungen.html
  └── api/contact.php     ← Kontaktformular-Handler

fillqr.de/app (PHP-App - Multi-Tenant)
  ├── public/
  │   ├── index.php       ← Mitgliedsantrag-Formular (Tenant-spezifisch)
  │   ├── submit.php      ← Formular-Verarbeitung + DB-Insert
  │   ├── thanks.html     ← Danke-Seite
  │   └── admin/
  │       ├── index.php   ← Antrags-Liste (pro Verein)
  │       ├── detail.php  ← Antrags-Details
  │       └── update_status.php
  └── src/
      ├── config.php      ← DB-Credentials (NICHT in Git!)
      ├── app.php         ← Geschaeftslogik, Helper-Funktionen
      └── tenant.php      ← Multi-Tenant Logik
```

---

## Deployment

### Landingpage

```bash
cd c:/ERPBuddy/web/fillqr/landing/public
scp index.html datenschutz.html impressum.html nutzungsbedingungen.html variomedia:~/fillqr/landing/public/
scp css/style.css variomedia:~/fillqr/landing/public/css/
scp js/main.js variomedia:~/fillqr/landing/public/js/
```

### PHP-App

```bash
cd c:/ERPBuddy/web/fillqr/app
# ACHTUNG: config.php NICHT deployen (liegt nur auf Server)
scp public/*.php variomedia:~/fillqr/app/public/
scp public/admin/*.php variomedia:~/fillqr/app/public/admin/
scp src/app.php src/tenant.php variomedia:~/fillqr/app/src/
scp public/assets/css/base.css variomedia:~/fillqr/app/public/assets/css/
```

---

## Datenbank (MySQL bei Variomedia)

### Wichtige Tabellen

| Tabelle | Zweck |
|---------|-------|
| tbl_tenant | Vereins-Stammdaten, Subdomain, Config |
| tbl_tenant_domain | Domain-Mapping pro Tenant |
| tbl_application | Mitgliedsantraege |
| tbl_application_event | Event-Log (Statuswechsel, Warnungen) |
| tbl_app_user | Admin-Benutzer (geplant) |

### Antrags-Status-Flow

```
NEW → REVIEWED → EXPORTED → ARCHIVED
```

---

## Offene Punkte (aus fillqr_changelog.txt v0.1.2)

### Erledigt
- [x] Serverseitige Validierung (Pflichtfelder, Fehlermeldungen)
- [x] Geschaeftsregeln (Alter, Minderjahrige, SEPA, Eintrittstermin)
- [x] Warn-Flags Grundstruktur

### Offen - Admin Backend
- [ ] Statuswechsel (NEW/REVIEWED/EXPORTED/ARCHIVED)
- [ ] Filter und Suche in Admin-Liste
- [ ] Warnungen im Admin anzeigen

### Offen - Sicherheit
- [ ] Login ueber tbl_app_user (E-Mail + Passwort)
- [ ] Rechte und CSRF-Tokens
- [ ] Rate-Limiting

### Offen - Multi-Tenant Cockpit
- [ ] Cockpit-Grundseite (alle Tenants uebersicht)
- [ ] Onboarding-Formular (neuen Verein anlegen)

### Offen - Komfort
- [ ] Branding je Verein (Logo, Farben)
- [ ] Foto-Upload (Mitgliedsbild)

---

## Rechtliches (Stand: Februar 2026)

- Preise: 34,95 EUR/Monat + 99 EUR Design (inkl. MwSt)
- B2B-Ausrichtung: Verbraucher (§13 BGB) ausgeschlossen
- Durchgehend Du-Form
- USt-IdNr. im Impressum (DE370438727)
- AVV-Hinweis vorhanden
- TMG → DDG, TTDSG → TDDDG (Gesetzesaenderung Mai 2024)

---

## Credentials

| Name | Typ | Wo |
|------|-----|----|
| MySQL DB | DB-Login | src/config.php (nur auf Server) |
| SMTP | E-Mail-Versand | src/config.php (geplant) |

---

*Erstellt: 2026-02-10*
*Zuletzt aktualisiert: 2026-02-10*
