# SureCart Theme and Plugin Licensing SDK

## Installation

Clone the repository in your project.

```bash
cd /path/to/your/project/folder
git clone https://github.com/surecart/wordpress-sdk.git licensing
```
Or, you can download the [repository zip file](https://github.com/surecart/wordpress-sdk/releases/latest), and place it in the licensing folder of your plugin or theme.

Now include the dependencies in your plugin/theme.

```php
if ( ! class_exists( 'SureCart\Licensing\Client' ) ) {
    require_once __DIR__ . '/licensing/src/Client.php';
}
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
    "changelog": "<h4>1.0 – July 20, 2022</h4><ul><li>Bug fixes.</li><li>Initial release.</li></ul>",
    "frequently asked questions": "<h4>Question</h4><p>Answer</p>"
  },
  "icons": {
    "1x": "https://example.com/assets/icon-128.png",
    "2x": "https://example.com/assets/icon-256.png"
  },
  "banners": {
    "low": "https://example.com/assets/banner-772x250.png",
    "high": "https://example.com/assets/banner-1544x500.png"
  }
}
```

### ⚠️ Important
In order for updates to work, the `slug` in release.json must match the **folder name** of your plugin or theme. 
So if for example your plugin folder name is `ralphs-biscuits`, the `slug` in release.json must also be `ralphs-biscuits`.

Ensure that the SDK is loaded and initialized on the `init` hook in your plugin or theme to maintain proper functionality and integration.


## Usage Example

Please refer to the **installation** step before start using the class.

```php

add_action('init', function(){
	if ( ! class_exists( 'SureCart\Licensing\Client' ) ) {
		require_once __DIR__ . '/licensing/src/Client.php';
	}
	
	// initialize client with your plugin name and your public token.
	$client = new \SureCart\Licensing\Client( 'Your Plugin', 'pt_jzieNYQdE5LMAxksscgU6H4', __FILE__ );
	
	// set your textdomain.
	$client->set_textdomain( 'your-textdomain' );
	
	// add the pre-built license settings page.
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
});
```

Make sure you call this function directly, never use any action hook to call this function.

> For plugins example code that needs to be used on your main plugin file.
> For themes example code that needs to be used on your themes `functions.php` file.



## More Usage

```php
$client = new \SureCart\Licensing\Client( 'Twenty Twelve', 'pt_jzieNYQdE5LMAxksscgU6H4', __FILE__ );
```

## Set textdomain

You may set your own textdomain to translate text.

```php
$client->set_textdomain( 'your-project-textdomain' );
```

## Example Plugin
[surecart-plugin-license.zip](https://github.com/user-attachments/files/21472250/surecart-plugin-license.zip)

## Example Theme
[surecart-theme-license.zip](https://github.com/user-attachments/files/21472254/surecart-theme-license.zip)
