# Documentation et Guide d'Installation : Portail Captif Omada / FreeRADIUS

Ce projet décrit l'architecture et la procédure de mise en place d'un **portail captif d'authentification** géré par un contrôle centralisé TP-Link Omada, avec authentification externe via FreeRADIUS et hébergement de la page web sur un Raspberry Pi.

---

## 📋 Table des Matières

1. [Présentation de l'Architecture](#-présentation-de-larchitecture)
   - [Matériel & Équipements](#matériel--équipements)
   - [Plan d'Adressage IP](#plan-dadressage-ip)
   - [Flux d'Authentification (Diagramme de séquence)](#flux-dauthentification)
2. [Prérequis Système & Dépendances](#-prérequis-système--dépendances)
3. [Installation & Configuration du Serveur (Raspberry Pi)](#-installation--configuration-du-serveur-raspberry-pi)
   - [1. Attributions IP Statique](#1-attributions-ip-statique)
   - [2. Installation des Paquets et Dépendances](#2-installation-des-paquets-et-dépendances)
   - [3. Génération des Certificats SSL/TLS](#3-génération-des-certificats-ssltls)
   - [4. Configuration de FreeRADIUS](#4-configuration-de-freeradius)
   - [5. Configuration du Serveur Web Nginx & PHP](#5-configuration-du-serveur-web-nginx--php)
4. [Configuration du Contrôleur Omada](#-configuration-du-contrôleur-omada)
   - [1. Profil RADIUS](#1-profil-radius)
   - [2. Configuration du Portail Captif](#2-configuration-du-portail-captif)
5. [Maintenance et Fichiers de Log](#-maintenance-et-fichiers-de-log)

---

## 📐 Présentation de l'Architecture

### Matériel & Équipements

| Équipement | Marque / Modèle | Nom / Rôle | Adresse IP |
| :--- | :--- | :--- | :--- |
| **Routeur** | Cuby R700 | RSMA-R700 / Passerelle | `192.168.10.1` |
| **Contrôleur** | Omada V5.15.8.2 | RESIDENCE-RSMA | `192.168.10.219` |
| **Serveur** | Raspberry Pi 4 - x64 | Serveur Web (Nginx) + RADIUS | `192.168.10.220` |
| **Point d'accès 1** | TP-Link EAP660 HD (EU) v1.0 | Born-1 | `192.168.10.221` |
| **Point d'accès 2** | TP-Link EAP660 HD (EU) v1.0 | Born-2 | `192.168.10.222` |

### Plan d'Adressage IP & DHCP

- **Réseau local (LAN) / Admin :** `192.168.10.0/24`
- **Plage DHCP pour les clients Wi-Fi :** `192.168.10.50` à `192.168.10.150`
- **Durée de bail DHCP (Lease time) :** 24 Heures

---

### Flux d'Authentification

```
[ Client ] ---> (1) Tente d'accéder à Internet
[ Omada  ] ---> (2-3) Intercepte & Redirige vers la page du Portail Web (Serveur)
[ Serveur] ---> (4-6) Affiche la page HTML/PHP (http://192.168.10.220/portal)
[ Client ] ---> (7) Saisit et soumet Identifiant / Mot de passe
[ Serveur] ---> (8) Envoie la requête d'authentification à FreeRADIUS (Port 1812)
[ RADIUS ] ---> (9) Valide les accès (AAA) et renvoie le résultat (Access-Accept)
[ Omada  ] ---> (10) Redirige vers la page de succès puis vers la destination initiale
```

---

## 🛠 Prérequis Système & Dépendances

Sur le Raspberry Pi (Debian/Raspberry Pi OS 64-bit), assurez-vous d'avoir les autorisations `root` ou d'utiliser un utilisateur avec privilèges `sudo`.

---

## ⚙️ Installation & Configuration du Serveur (Raspberry Pi)

### 1. Attributions IP Statique

Définissez une adresse IP fixe sur l'interface réseau filaire du Raspberry Pi.

```bash
sudo nmcli connection modify "Wired connection 1" \
    ipv4.addresses 192.168.10.220/24 \
    ipv4.gateway 192.168.10.1 \
    ipv4.dns "8.8.8.8 1.1.1.1" \
    ipv4.method manual

sudo nmcli connection down "Wired connection 1"
sudo nmcli connection up "Wired connection 1"
```

### 2. Installation des Paquets et Dépendances

Mettez à jour le système et installez les dépendances réseau, le serveur web Nginx, PHP, et les outils nécessaires.

```bash
# Mise à jour du système
sudo apt update && sudo apt upgrade -y

# Installation des paquets système et réseau
sudo apt install -y freeradius dnsmasq nginx iptables-persistent

# Installation du stack PHP
sudo apt install -y php php-fpm php-cli php-common php-curl php-mbstring php-xml php-mysql

# Création des arborescences de répertoires
sudo mkdir -p /var/www/captive-portal 
sudo mkdir -p /usr/local/bin
```

### 3. Génération des Certificats SSL/TLS

Afin de sécuriser les flux HTTPS vers le portail captif, générez un certificat auto-signé :

```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/captive-portal.key \
  -out /etc/ssl/certs/captive-portal.crt \
  -subj "/C=FR/ST=France/L=Paris/O=Hotel/CN=192.168.10.220"
```

### 4. Configuration de FreeRADIUS

Les fichiers de configuration clés de FreeRADIUS se situent dans `/etc/freeradius/3.0/` :

* **`/etc/freeradius/3.0/clients.conf`** : Déclare les équipements réseau autorisés à interroger le serveur RADIUS (le contrôleur Omada et les APs).
  
  ```text
  client omada-controller {
      ipaddr = 192.168.10.219
      secret = <Votre_Mot_De_Passe_RADIUS>
  }
  ```

* **`/etc/freeradius/3.0/users`** : Contient les comptes utilisateurs autorisés et leurs droits.

### 5. Configuration du Serveur Web Nginx & PHP

* **Fichier de configuration Nginx :** `/etc/nginx/sites-available/captive-portal`
* **Racine des fichiers Web :** `/var/www/captive-portal/`
* **Traitement des logs clients :** `/var/www/captive-portal/log_client.php`

Pensez à activer la configuration Nginx en créant un lien symbolique :
```bash
sudo ln -s /etc/nginx/sites-available/captive-portal /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

---

## 🌐 Configuration du Contrôleur Omada

### 1. Profil RADIUS

Sur l'interface de votre contrôleur Omada (**`https://192.168.10.219`**) :

1. Naviguez dans **Settings > Authentication > RADIUS Profile**.
2. Créez un profil nommé `Berry-Radius` :
   - **Authentication Server IP :** `192.168.10.220`
   - **Authentication Port :** `1812`
   - **Authentication Password :** *(Clé partagée définie dans clients.conf)*
   - **RADIUS Accounting :** Activer (Checked)
   - **Accounting Server IP :** `192.168.10.220`
   - **Accounting Port :** `1813`
   - **Timeout :** `3` secondes

### 2. Configuration du Portail Captif

Naviguez dans **Settings > Authentication > Portal** et configurez comme suit :

* **Portal Name :** `SMA-Portal`
* **SSID & Network :** `RESIDENCE-RSMA`
* **Authentication Type :** `RADIUS Server`
* **Authentication Timeout :** `1 Day`
* **RADIUS Profile :** `Berry-Radius`
* **NAS ID :** `TP-Link`
* **Authentication Mode :** `PAP`
* **Portal Customization :** `External Web Portal`
  * **External Portal URL :** `http://192.168.10.220/portal`
* **HTTPS Redirection :** Enable (Coché)
* **Landing Page :** Redirection vers `The Promotional URL` : `http://google.com` (ou URL d'origine)

---

## 📝 Maintenance et Fichiers de Log

- **Suivi des connexions RADIUS en temps réel :**
  ```bash
  sudo freeradius -X
  ```
- **Fichiers de logs Web & Port d'accès :**
  `/var/www/captive-portal/log_client.php`
- **Logs Nginx :**
  `/var/log/nginx/access.log` et `/var/log/nginx/error.log`