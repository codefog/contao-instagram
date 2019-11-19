# Instagram – Documentation

## Create an Instagram app

First of all you have to create the Instagram app. For that please follow the [official Getting Started guide](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started)
up until point 3 (inclusive).

One important thing to note is that for all app URIs listed:

- Valid OAuth Redirect URIs
- Deauthorize Callback URL
- Data Deletion Request Callback URL

you have to enter your domain name + `/contao` suffix, e.g. `https://domain.tld/contao`: 

![](images/instagram-1.png)

Here you should also copy the *Instagram App ID* and *Instagram App Secret* keys to your clipboard.


## Create a frontend module

Now go to the Contao backend and create the `Instagram` front end module. Fill in the necessary data and save the record.

**Note:** be sure to check the `Request access token` box!

![](images/instagram-2.png)

If you have configured your app properly, you should now see the screen prompting you for the authorization.
Click the green button to authorize yourself for your app and you should be taken back to the Contao backend.

![](images/instagram-3.png)

Please ensure that the `Instagram access token` is now filled in. You can now safely add the module to the page.


## Template data

The displayed template data out of box is very simple, as only the images are displayed. If you need more information,
you should check out the `$this->items` and `$this->user` variables.

You can do that by dumping the variables inside `mod_cfg_instagram.html5` template:

```php
<?php $this->showTemplateVars(); ?>
```


## Data restrictions

Before you report any bugs regarding the missing Instagram feed data, be sure that you have read the official
documentation that contains information about the data you can obtain from the API:

1. https://developers.facebook.com/docs/instagram-basic-display-api/


## About errors

If at some point the extension does not work make sure to check the system logs.
