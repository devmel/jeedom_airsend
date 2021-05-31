Description 
===

Plugin Jeedom permettant d’utiliser un (ou plusieurs) appareil AirSend en émission et réception RF433. Ce plugin est totalement autonome et ne nécessite ni connexion internet, ni cloud pour faire la liaison avec le AirSend connecté sur le réseau local.

Configuration du plugin
===

Après téléchargement du plugin, il faut l'activer puis installer les "Dépendances" et démarrer le "Démon".

Configuration des équipements
===

La configuration des équipements est accessible à partir du menu plugins -> protocole domotique -> AirSend.

Les appareils sans fils doivent au préalable être configurés depuis l'application mobile et/ou airsend.cloud.


### Import depuis android

L'import mobile est de loin la solution la plus simple.

- Dans Jeedom cliquez sur "Import mobile"
- Connectez votre mobile sur le réseau wifi en liaison avec votre Jeedom.
- Depuis l'application mobile, accédez au menu "Paramètres" (en haut à droite).
- Cliquez sur "Easy Scan", cela ouvre une application externe de scan de QRCode.
- Scannez le QRCode affiché dans Jeedom
- Acceptez l'avertissement de sécurité

Toute votre configuration mobile (appareils AirSend et appareils sans fil) est copiée dans Jeedom, il vous reste à "activer", rendre "visible" et choisir l'"objet parent" pour chaque appareil.

### Ajout d'un appareil AirSend

- Cliquez sur "Ajouter" et donnez un nom à cet appareil.
- Choisissez un "Objet parent", les sondes de l'appareil s'afficheront à cet endroit.
- Cliquez sur "Activer" et "Visible".
- Choisissez "Type d'appareil" : "Boiter AirSend".
- Renseignez le "LocalIP" et "Password" se trouvant au dos de votre appareil AirSend.
- Cochez "Passerelle Internet" si votre AirSend n'est pas sur le réseau local.
- Renseignez "Adresse Secondaire" par l'ipv4 du AirSend si la communication ipv6 locale ne fonctionne pas (avec docker par exemple).
- Cochez "Ecoute permanente" si vous souhaitez basculer en mode réception radio permanente (voir ci-dessous).
- Cochez "Inclusion automatique" pour que les appareils détectés soit ajoutés directement.
- Pour finir cliquez sur "Sauver/Générer".

A ce stade votre appareil est connecté et vous devriez voir la température et l'éclairement ambiant dans votre "Dashboard".

### Ajout des appareils sans fil

> **Ajout manuel de chaque télécommande avec le Protocole et l'Adresse**
>
> Vous retrouvez ces informations en modifiant chaque télécommande dans l'application mobile. Cependant cette méthode ne fonctionne pas correctement avec les télécommandes 1-bouton.

> **Import d'un fichier**
>
> Depuis l'application, accédez au menu "Paramètres" (en haut à droite), puis choisissez "Exporter". Le plus simple est de vous l'envoyer par mail. 
> 
> Depuis airsend.cloud, accédez au menu "Import/Export", cochez les noms à exporter puis choisissez "Exporter". 
> 
> Télécharger ce fichier et l'importer dans Jeedom.

Vos appareils sans fil sont maintenant dans Jeedom, il vous reste à les "activer", les rendre "visible" et choisir votre "objet parent" pour le placement dans le "Dashboard".

Fonctionnement du mode "écoute permanente"
===
Avec ce mode le AirSend bascule en mode réception radio permanente et remonte vers jeedom les informations décodées. 
Ainsi il est possible d'avoir un état des télécommandes réelles, capteurs et sondes compatibles. 

> **Inclusion automatique**
>
> Pour ajouter dans jeedom ces télécommandes réelles (ou sondes) il faut utiliser temporairement le mode "inclusion automatique" et trouver le type de télécommande le plus pertinent. Certaines télécommandes de volet roulant seront reconnues comme 3 télécommandes 1-bouton. 

> **Propagation de l'état**
>
> Une fois ces télécommandes ajoutées dans jeedom, l'option "Propager l'état vers" permet d'assigner cet état à la télécommande qui pilote l'automatisme. 

Ce mode permet d'avoir un état très proche de l'état réel ainsi que les informations provenant de sondes et capteurs.
Le déclenchement de scénarios est également possible à partir du nouvel état ou informations reçus.

Liste des appareils sans fil compatibles
===

Vous trouverez la liste des appareils sans fil compatibles : <https://devmel.com/fr/airsend.html#compatibility>

Conseils
===
- Le mode "inclusion automatique" peut vite encombrer le plugin de nouvelles télécommandes, il vaut mieux l'activer temporairement.
