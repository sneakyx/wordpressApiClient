## Verwendung

Derzeit gibt es 3 Funktionen

### Login
Für das Login muss zunächst ein Objekt erzeugt werden:

`$wordpressApiClient = new WordpressApiClient('username', 'password', 'https://your-wordpress-basic.url', array('Filter', 'für', 'Hauptkategorien'));`
Der Konstruktor loggt sich direkt ein, weitere Requests sind nun möglich. Mit Hilfe des vierten Parameters ist es möglich, den Zugriff auf eine bestimmte Hauptkategorie (und deren Unterkategorien) einzuschränken.

### Beliebige Abfrage
`$wordpressApiClient->getApiData($path,$returnAsArray)`
Die Variable `$path` kann wie in [https://developer.wordpress.org/rest-api/reference/](https://developer.wordpress.org/rest-api/reference/) beschrieben genutzt werden- Standard ist `"posts"`

Wenn `$returnAsArray` false ist, wird ein JSON-encodeter String zurückgegeben. (Standard ist `true`), also kommt normalerwweise ein Array zurück.

Ich habe es mit `posts`, `users` und `categories` getestet, andere sollten aber auch gehen.

### Alle Kategorien mit weiteren, nützlichen Informationen erhalten

`$wordpressApiClient->getOrderedCategories()` gibt alle Kategorien zurück und iwe diese miteinander verknüpft sind. 
Von der Wordpess-API bekommen wir lediglich die Information, ob es einen `parent` gibt (bzw, welche ID dieser hat). Aber nur der direkte Vorfahr (Eltern) wird mitgeliefert.
Diese Funktion ergänzt ausserdem `ancestors`, also alle Vorfahren in der richtigen Reihenfolge (also als Pfad), zudem ist die Tiefe mit `depth` direkt abrufbar. 
Diese Information kann z.B. für das Einrücken mit css genutzt werden.  
Zudem können auch auf die direkten Kinder (erste Generation) mit `children` zugeriffen werden. 
Alle Kinder und (Ur-...)Enkel sind über `successors` aufrufbar. Dies wird für die nächste Funktion genutzt. 

### Alle Beiträge oder bestimmte Beiträge zurückgeben 
`$wordpressApiclient->getPosts()` hat viele Einsatzgebiete.
Wird die Funktion ohne weitere Parameter eingesetzt, kommen die 10 neusten Beiträge zurück- wenn beim Konstruktor eine Einschränkung für die Hauptkategorie hinzugefügt wurd, kommen nur die Beiträge aus dieser Kategorie (oder einer der Unterkategorien zurück).

Der erste Parameter ist der Kategorienfilter, es muss ein **Array** mit IDs oder slugs (auch gemischt) angegeben werden (auch bei nur einer ID/einem slug).
Die deutsche Wordpress-Version spricht hier übrigens von "Titelform".

Der zweite Parameter sorgt dafür, dass auch innerhalb der Unterkategorien nach passenden Beiträgen gesucht wird. 
Standard ist `true`- soll nur genau in dieser einen Kategorie gesucht werden, muss `false` gesetzt werden. 

Der dritte Parameter kann so genutzt werden, um weitere (Filter-)Parameter anzuhängen:
```
array(
'parameter1'=>'value1',
'parameter2'=>'value2'
)
```

Wenn ein Parameter nur mit Schlüssel und ohne Wert benutzt werden soll (z.B. `_embed`) kann `true` die Lösung sein:
```
array(
'_embed`=>true
)
``` 

Wenn was fehlt, öffne einfach ein Ticket auf github (auf englisch): [https://github.com/sneakyx/wordpressApiClient/issues](https://github.com/sneakyx/wordpressApiClient/issues)
