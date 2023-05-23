# SureCart Theme and Plugin Licensing SDK

## Pre-requisites
1. Enable Licensing - https://surecart.com/docs/licensing/
1. Minimum PHP Version - `7.0`

## Installation

Clone the repository in your project.

```bash
cd /path/to/your/project/folder
git clone https://github.com/surecart/wordpress-sdk.git licensing
```

Now include the dependencies in your plugin/theme.

```php
require_once __DIR__ . '/licensing/SureCartSdkLoader.php';
```

## Include a release.json

Add a `release.json` file to the root of your plugin or theme project. 
`release.json` requires the following: 

| Property | Description |
| ----------- | ----------- |
| `name` | Your plugin or theme display name.  |
| `slug` | Your plugin or theme slug. *IMPORTANT* This must match the folder name of your project. |
|`author`| HTML to be used for the author of the plugin.|
|`author_profile`| A url to your author profile.|
|`version`| The version of your plugin or theme.|
|`requires`| The required WordPress version.|
|`tested`| The version of WordPress the plugin has been tested with.|
|`requires_php`| The required php version.|
|`sections`| An array of sections to tab through in the update UI.|

Sections will require a `changelog` property with an html string of your changelog.

### release.json example

```json
{
  "name": "SureCart Example Plugin",
  "slug": "surecart-plugin-example",
  "author": "<a href='https://surecart.com'>SureCart</a>",
  "author_profile": "https://surecart.com",
  "version": "0.9.0",
  "requires": "5.6",
  "tested": "6.1.0",
  "requires_php": "5.3",
  "sections": {
    "description": "This is my plugin description.",
    "changelog": "<h4>1.0 –  July 20, 2022</h4><ul><li>Bug fixes.</li><li>Initital release.</li></ul>",
    "frequently asked questions": "<h4>Question<h4><p>Answer</p>"
  }
}
```


## Usage Example

Please refer to the **installation** step before start using the class.

```php

require_once __DIR__ . '/licensing/SureCartSdkLoader.php';

// initialize client with your plugin name.
SureCartSdkLoader::instance()->initialize_client( 'Your plugin name', __FILE__ );

// Get the client.
$client = SureCartSdkLoader::instance()->get_client();

// Set your textdomain.
$client->set_textdomain( 'your-textdomain' );

// Add the pre-built license settings page.
$client->settings()->add_page( 
    [
	'type'                 => 'submenu', // Can be: menu, options, submenu.
	'parent_slug'          => 'your-plugin-menu-slug', // add your plugin menu slug.
	'page_title'           => 'Manage License',
	'menu_title'           => 'Manage License',
	'capability'           => 'manage_options',
	'menu_slug'            => $client->slug . '-manage-license',
	'icon_url'             => '',
	'position'             => null,
	'parent_slug'          => '',
	'activated_redirect'   => admin_url( 'admin.php?page=my-plugin-page' ), // should you want to redirect on activation of license.
	'deactivated_redirect' => admin_url( 'admin.php?page=my-plugin-deactivation-page' ), // should you want to redirect on detactivation of license.
    ] 
);
```

Make sure you call this function directly, never use any action hook to call this function.

> For plugins example code that needs to be used on your main plugin file.
> For themes example code that needs to be used on your themes `functions.php` file.


## More Usage

```php
# For theme
SureCartSdkLoader::instance()->initialize_client( 'Twenty Twelve', __FILE__ );

$client = SureCartSdkLoader::instance()->get_client();
```

## Set textdomain

You may set your own textdomain to translate text.

```php
$client->set_textdomain( 'your-project-textdomain' );
```

## Get the license activation information
```php
$client->settings()->get_activation();
```

#### Activation Response Demo

```json
{
    "id" : "xxxxxxxxxxxxx",
    "object" : "activation",
    "counted" : true,
    "name" : "SiteName",
    "fingerprint" : "http://site.com",
    "license" : "xxxxxxxxxx",
    "created_at" : 1683786871,
    "updated_at" : 1683786871
}
```
