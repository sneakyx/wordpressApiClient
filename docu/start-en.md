## Usage
At the moment, there are 3 functions implemented:

### Login
First, you have to create a new WordpressApiClient-Object:

`$wordpressApiClient = new WordpressApiClient('username', 'password', 'https://your-wordpress-basic.url');`
The connector connects directly to the api and all other requests are now possible.

### Simple request
`$wordpressApiClient->getApiData($path,$returnAsArray)`
You can use `$path` as described in [https://developer.wordpress.org/rest-api/reference/](https://developer.wordpress.org/rest-api/reference/) - standard is `"posts"`

If `$returnAsArray` is set to false, it returns a json-encoded string. (standard is `true`), so an array is returned.

This should work for all get requests- I tested it for `posts`, `users`and `categories`.

### get categories with all relevant connected infos

`$wordpressApiClient->getOrderedCategories()` returns all categories and meta infos about how they are connected. 
The wordpress API only returns 1.generation parent, here you get also all grand- and great-grandparents (and before) - in key `ancestors` (right path order) and the `depth` of category.
The info about depth can be directly used for css-alignment-infos.
You get also the direct `children` (1. generation) and all `successors` (all generations). This can be used for a special search (see next chapter).

### get all /filtered posts
`$wordpressApiclient->getPosts()` is a very powerfull function.
If You use it without any parameters, you get ~~all~~ 10 (pagination(!)) posts by the api.

The first parameter is a filter for categories. You have go give an **array** of category-ids. 

The second parameter adds all successors to the filter-categories, so if you have for example a category "news" with subcategories "computer", 
"electronics", just add the id of "news" to get all posts- even from the subcategories.
If you just want the posts for this specific category, just set it to false.
(Standard is true)   

The third parameter is for all other parameters send to the api. Use it that way:
```
array(
'parameter1'=>'value1',
'parameter2'=>'value2'
)
```

Is there something missing? Open an issue on github: [https://github.com/sneakyx/wordpressApiClient/issues](https://github.com/sneakyx/wordpressApiClient/issues)
