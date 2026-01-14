=== LaserTagPro Buchung ===
Contributors: yourname
Tags: booking, calendar, reservation, appointment, lasertag
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Terminbuchungssystem mit DAV-Kalender-Integration für LaserTagPro.

== Description ==

LaserTagPro Buchung ist ein vollständiges Terminbuchungssystem für WordPress, das sich nahtlos in Ihre Website integriert. Das Plugin ermöglicht es Besuchern, Termine zu buchen, während die verfügbaren Zeiten aus einem DAV-Kalender abgerufen werden.

= Hauptfunktionen =

* **DAV-Kalender-Integration**: Verbindung mit CalDAV-Servern zur Abfrage verfügbarer Termine
* **Flexible Buchungsdauer**: Termine können zwischen 1 und 3 Stunden gebucht werden
* **Intelligente Verfügbarkeitsprüfung**: Wenn 3x 1-Stunden-Termine verfügbar sind, kann auch 1x 3-Stunden-Termin gebucht werden
* **Spielmodi/Packages**: Verschiedene Spielmodi mit konfigurierbaren Eigenschaften
* **Monatskalender-Ansicht**: Übersichtliche Darstellung aller verfügbaren Termine
* **Buchungsformular**: Umfassendes Formular mit Name, E-Mail, Telefon, Nachricht, Personenzahl und Spielmodus
* **E-Mail-Benachrichtigungen**: Automatische Bestätigungs-E-Mails für Buchungen und Stornierungen
* **Stornierungsfunktion**: Kunden können Reservierungen über einen Link in der E-Mail stornieren
* **Admin-Backend**: Vollständige Verwaltung von Reservierungen, Spielmodi und Einstellungen
* **Shortcode & Widget**: Einfache Integration über Shortcode oder Widget
* **Responsive Design**: Mobile-first Design für alle Geräte
* **Barrierefreiheit**: WCAG 2.1 AA konform

= Installation =

1. Laden Sie das Plugin hoch oder installieren Sie es über das WordPress-Plugin-Verzeichnis
2. Aktivieren Sie das Plugin über das Menü 'Plugins' in WordPress
3. Gehen Sie zu 'LaserTagPro' > 'Einstellungen' und konfigurieren Sie Ihre DAV-Kalender-Verbindung
4. Fügen Sie den Shortcode `[lasertagpro_kalender]` zu einer Seite hinzu oder verwenden Sie das Widget

= Verwendung =

= Shortcode =

Verwenden Sie den Shortcode `[lasertagpro_kalender]` auf jeder Seite oder in jedem Beitrag, um den Buchungskalender anzuzeigen.

= Widget =

Gehen Sie zu 'Design' > 'Widgets' und fügen Sie das 'LaserTagPro Kalender'-Widget zu Ihrem Sidebar oder Footer hinzu.

= Einstellungen =

Unter 'LaserTagPro' > 'Einstellungen' können Sie folgende Optionen konfigurieren:

* DAV-Kalender-URL
* DAV-Benutzername und Passwort
* Buchungszeiten (Start- und Endstunde)
* E-Mail-Einstellungen

= Spielmodi =

Unter 'LaserTagPro' > 'Spielmodi' können Sie verschiedene Spielmodi/Packages erstellen und verwalten. Jeder Spielmodus kann eine Standard-Dauer, einen Preis und eine Beschreibung haben.

= Häufig gestellte Fragen =

= Wie verbinde ich das Plugin mit meinem DAV-Kalender? =

Gehen Sie zu 'LaserTagPro' > 'Einstellungen' und geben Sie Ihre DAV-Kalender-URL, Ihren Benutzernamen und Ihr Passwort ein. Das Plugin verwendet diese Informationen, um verfügbare Termine abzurufen.

= Kann ich mehrere Spielmodi anbieten? =

Ja, Sie können unter 'LaserTagPro' > 'Spielmodi' beliebig viele Spielmodi erstellen. Jeder Spielmodus kann eine eigene Dauer, einen eigenen Preis und eine eigene Beschreibung haben.

= Wie funktioniert die flexible Buchungsdauer? =

Wenn ein Kunde einen 3-Stunden-Termin buchen möchte, prüft das Plugin, ob 3 aufeinanderfolgende 1-Stunden-Slots verfügbar sind. Wenn ja, kann der Termin gebucht werden.

= Können Kunden ihre Reservierungen stornieren? =

Ja, Kunden erhalten nach der Buchung eine E-Mail mit einem Stornierungslink. Über diesen Link können sie ihre Reservierung stornieren und erhalten eine Bestätigung per E-Mail.

= Changelog =

= 1.0.0 =
* Erste Veröffentlichung
* DAV-Kalender-Integration
* Buchungssystem mit flexibler Dauer
* Spielmodi-Verwaltung
* E-Mail-Benachrichtigungen
* Stornierungsfunktion
* Admin-Backend
* Shortcode und Widget
* Responsive Design
* Barrierefreie Implementierung

== Upgrade Notice ==

= 1.0.0 =
Erste Veröffentlichung des Plugins.




