# ilpost-addict
Generatore di feed rss dei podcast de IlPost per l'utilizzo in app di podcast che supportano l'autenticazione.
## Cos'è
Una pagina php che restituisce il feed rss dei podcast de [IlPost](https://www.ilpost.it) aggiungendo un'enclosure con l'indirizzo del file mp3, in modo da poterlo utilizzare nelle app di podcast (testato su [Podcast Addict](https://www.podcastaddict.com)). E' necessario impostare Username e Password (attivando la spunta _Authentication (Premium Podcast)_ in Podcast Addict) e verranno inclusi gli episodi a cui si ha accesso.
## A cosa serve
Il giornale online Il Post, produce numerosi [podcast](https://www.ilpost.it/podcasts/) di cui alcuni sono gratuiti e liberamente accessibili, mentre altri sono disponibili solamente per gli abbonati. Questi ultimi possono essere ascoltati sull'app del post o tramite browser accedendo all'area riservata del sito. Purtroppo non è stata prevista la possibilità di utilizzare un'app di podcast per l'ascolto dei podcast riservati agli abbonati. A me queste modalità di fruizione risultano scomode perché mi obbligano ad utilizzare 2 differenti app per i podcast de Il Post e per tutti gli altri che ascolto.
## Come funziona
La pagina php inclusa in questo repository esegue le seguenti operazioni:
* Effettua il login sul sito de Il Post, con le credenziali specificate nella richiesta http
* Richiede la pagina del podcast contenente i link ai file .mp3 delle puntate
* Richiede il feed rss del podcast (che è disponibile anche per gli utenti non autenticati, ma non contiene i link ai file .mp3 delle puntate)
* Restituisce il feed rss del podcast, aggiungendo un tag _enclosure_ con il link alle puntate recuperato dalla pagina del podcast; viene anche aggiunto il tag _itunes:block_ per segnalare che il podcast non è pubblico.
## Come si usa
### Installazione
Il file _index.php_ può essere caricato su un server web con i requisiti necessari, creando le directory _log_ e _cookies_. A questo punto è possibile aggiungere i podcast in _Podcast Addict_ selezionando come sorgente il feed rss restituito dalla pagina creata.
### Parametri
L'unico parametro che è possibile impostare nell'url è _podcast_ che deve essere impostato con il nome del podcast di cui si vuole recuperare il feed. Se non viene specificato nessun parametro, verranno proposti gli episodi di _morning_.
Per esempio, per recuperare il feed di _Tienimi bordone_ bisognerà puntare a:
>www.mioserver.it/ilpost-addict/?podcast=tienimi-bordone
### Requisiti
Sul server web è necessario che siano installati:
* PHP (testato su versione 7.4, ma non dovrebbero esserci particolari problemi con altre versioni)
* Modulo _curl_ per PHP
* Modulo _dom_ per PHP
### Altri programmi di podcast
In linea di principio è possibile utilizzare la pagina php, a patto che possano fare una richiesta http autenticata (basic authentication) al server web, tuttavia al momento non sono state testate applicazioni diverse da _Podcast Addict_ su Android.
## Istanze installate
E' possibile trovare un'istanza della pagina presso
>https://www.funandfood.it/podcast-ilpost/

Quindi per scaricare il feed di "Ci vuole una scienza" l'indirizzo è:
>https://www.funandfood.it/podcast-ilpost/?podcast=ci-vuole-una-scienza
