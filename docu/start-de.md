## Verwendung

Derzeit gibt es 9 Funktionen

### Login
Für das Login muss zunächst ein Objekt erzeugt werden:

`$wordpressApiClient = new WordpressApiClient('username', 'password', 'https://your-wordpress-basic.url', array('Filter', 'für', 'Hauptkategorien'));`
Der Konstruktor loggt sich direkt ein, weitere Requests sind nun möglich. Mit Hilfe des vierten Parameters ist es möglich, den Zugriff auf eine bestimmte Hauptkategorie (und deren Unterkategorien) einzuschränken.

### Beliebige Abfrage
`$wordpressApiClient->getApiData($path,$returnAsArray)`
Die Variable `$path` kann wie in [https://developer.wordpress.org/rest-api/reference/](https://developer.wordpress.org/rest-api/reference/) beschrieben genutzt werden- Standard ist `"posts"`

Wenn `$returnAsArray` false ist, wird ein JSON-encodeter String zurückgegeben. (Standard ist `true`), also kommt normalerweise ein Array zurück.

Ich habe es mit `posts`, `users` und `categories` getestet, andere sollten aber auch gehen.

Es können ggfls. nicht alle Elemente mit einem API Aufruf geholt werden. Pro Seite werden als Standard 10 Elemente zurückgeschickt, 
das Maximum liegt meines Wissens bei 100. (Parameter `per_page`)
Wenn es mehr als 100 Elemente gibt, müssen die Daten mit mehreren Aufrufen geholt werden. Dafür habe ich 2 Eigenschaften hinzugefügt.

`$wordpressApiClient->getTotalAmountLastCall()` darin ist die Anzahl der Elemente, die durch diesen Filter abgeholt werden können.
`$wordpressApiClient->getTotalPagesLastCall()` hiermit bekommst Du die Anzahl der Seiten durch die aktuelle Filterung.

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


### einzelnen Beitrag auslesen
`$wordpressApiClient->getPost()` gibt einen einzelnen Beitrag zurück.
Der erste Parameter `id` muss angegeben werden, der zweite Parameter `parameters` benötigt ein Array, wie bei Funktion getPosts() beschrieben.
`parameters` muss nicht angegeben werden, aber die Angabe von 
```
array(
'_embed`=>true
)
``` 
könnte sinnvoll sein, z.B. um auch die URL des Beitragsbildes zu erhalten. 
Mit dem dritten Parameter kann geprüft werden, ob der Beitrag in den eingeschränkten (Sub-)Kategorien enthalten ist. 

### Nach einem Dateinamen in den MediaOrdner suchen
Wenn eine Datei zu Wordpress als Media hochgeladen wird, ist der Pfad zu dieser Datei leider mit einem zusätzlichen Teilpfad bestehend aus dem Datum festgelegt.
Dieser Pfad kann meines Wissens nicht über die API direkt mit einer einfachen, einzelnen Abfrage gefunden werden.
Dafür gibt es die Funktion `$wordpressApiClient->getMediaByFilename()`.
Der erste Parameter muss den Dateinamen (bzw einen Teil davon) enthalten.
Mithilfe des zweiten Parameters kann die Suche auf Groß-/Kleinschreibung achten.

### Überprüfen, ob eine Beitrags-ID existiert
Um zu überprüfen, ob ein Beitrag oder ein Array von Beiträgen (noch) existiert, kann diese Funktion verwendet werden. 
Bei einem einzelnen Beitrag kommt ein einzelnes true/false zurück, wenn die Funktion mit einem Array von ids aufgerufen wird, kommt ein Array zurück, bei dem die Schlüssel die 
den aufgerufenen IDs entsprechen.
`$wordpressApiClient->checkIfPostsExist()`

### Schlagworte abholen
Die Funktion `$wordpressApiClient->getTags()` ermöglicht ein problemloses Abholen aller(!) Schlagworte. Diese sind nach der Häufigkeit der Verwendung sortiert.
Leider können auf diesem Weg die Schlagworte nicht nach einer Oberkategorie limitiert werden- es werden also auch Schlagworte angezeigt, 
die ausserhalb der zulässigen Kategorien in Beiträgen verwendet werden. 

### Benutzer holen
Mithilfe der Funktion `$wordpressApiClient->getUsers()` können alle Benutzer abgeholt werden, unabhängig von deren Verwendung innerhalb der Oberkategorien.


Wenn was fehlt, öffne einfach ein Ticket auf github (auf englisch): [https://github.com/sneakyx/wordpressApiClient/issues](https://github.com/sneakyx/wordpressApiClient/issues)
