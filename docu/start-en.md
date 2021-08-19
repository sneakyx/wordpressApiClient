## Usage
At the moment, there are 5 functions implemented:

### Login
First, you have to create a new WordpressApiClient-Object:

`$wordpressApiClient = new WordpressApiClient('username', 'password', 'https://your-wordpress-basic.url', array('restrict','to','some','main categories'));`
The connector connects directly to the api and all other requests are now possible. The fourth parameter sets a restriction to main categories. (You can use ids or slugs.) 

### Simple request
`$wordpressApiClient->getApiData($path,$returnAsArray)`
You can use `$path` as described in [https://developer.wordpress.org/rest-api/reference/](https://developer.wordpress.org/rest-api/reference/) - standard is `"posts"`

If `$returnAsArray` is set to false, it returns a json-encoded string. (standard is `true`), so an array is returned.

This should work for all get requests- I tested it for `posts`, `users`and `categories`.

Wordpress adds pagination. You can get more items with parameter `per_page` (standard is 10, maximum is 100). 
So, if you have more than 100, you have to get all pages. To make it easier for You, I added the properties
`$wordpressApiClient->getTotalAmountLastCall()` this returns the amount of all items in this filter.
`$wordpressApiClient->getTotalPagesLastCall()` returns the amount of pages with this filter. 

### get categories with all relevant connected infos

`$wordpressApiClient->getOrderedCategories()` returns all categories and meta infos about how they are connected. 
The wordpress API only returns 1.generation parent, here you get also all grand- and great-grandparents (and before) - in key `ancestors` (right path order) and the `depth` of category.
The info about depth can be directly used for css-alignment-infos.
You get also the direct `children` (1. generation) and all `successors` (all generations). This can be used for a special search (see next chapter).

### get all /filtered posts
`$wordpressApiclient->getPosts()` is a very powerful function.
If You use it without any parameters, you get 10  posts by the api (see Wordpress-API pagination). If the filter for main categories is set, only posts from these categories (and successor categories) are returned.

The first parameter is a filter for categories. You have go give an **array** of category-ids and/or category-slugs. 

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
If you want to add a parameter with key and without value like `_embed` maybe usage of `true` might help:
```
array(
'_embed`=>true
)
``` 

### get a single post
`$wordpressApiClient->getPost()` returns a single blog post entry.
The first parameter `id` is a must, second parameter `parameters` need an array, as described in function getPosts().
`parameter` can be left empty, but it could be wise to use 
```
array(
'_embed`=>true
)
``` 
to get also the URL of featured image (for example). 
The third parameter is usually set to true, which means that the function checks if 
a) restricted categories are set and 
b) one of the categories of the post is in restricted categories or subcategories


### Search for a filename
If you want to access a file, that was uploaded to wordpress, the URL consists also of the uploaded date.
As far as I know the wordpress API doesn't give you the possibility to search a file by filename with a single and simple API call.
You can use the funktion `$wordpressApiClient->getMediaByFilename()`.
First parameter is the (partial) filename, the second paramter toggles the case sensitivity.

Is there something missing? Open an issue on github: [https://github.com/sneakyx/wordpressApiClient/issues](https://github.com/sneakyx/wordpressApiClient/issues)
