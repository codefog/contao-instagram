# Instagram Bundle for Contao Open Source CMS

![](https://img.shields.io/packagist/v/codefog/contao-instagram.svg)
![](https://img.shields.io/packagist/l/codefog/contao-instagram.svg)
![](https://img.shields.io/packagist/dt/codefog/contao-instagram.svg)

Instagram is a bundle for the [Contao Open Source CMS](https://contao.org).

Contao bundle that allows to display the Instagram recent user feed on your website. It allows to specify you
the number of items displayed and comes up with a simple cache system.

**Note:** the bundle requires an Instagram account and Facebook App to work!

![](docs/images/preview.png)

## ! Important note !

Right now Instagram and Facebook [offer only 1-hour access tokens](https://developers.facebook.com/docs/instagram-basic-display-api/overview#instagram-user-access-tokens). 
That means your feed on the website will **not update automatically**. In order to update the feed, you will have to go 
to the module settings, check the `Request access token and update feed` checkbox and save the module.

You can track the issue progress here:

- https://developers.facebook.com/support/bugs/3109002399171119/
- https://developers.facebook.com/community/threads/2348548148794443/
- https://developers.facebook.com/community/threads/446420572897396/
- https://stackoverflow.com/questions/58539372/is-there-any-way-to-get-long-lived-code-access-tokens-for-instagram-basic-displa
- https://stackoverflow.com/questions/58501059/renewing-user-access-token-using-instagram-basic-display

## Installation

Install the bundle via Composer:

```
composer require codefog/contao-instagram
```

## Documentation

[Read the documentation](docs/README.md)

## Copyright

This project has been created and is maintained by [Codefog](https://codefog.pl).
