# API Toolbox
Base toolbox plugin for Api [Lovata.Shopaholic](https://octobercms.com/plugin/lovata-shopaholic) plugins

## Dependencies
This plugin depends on:

- [Lovata.Toolbox](https://octobercms.com/plugin/lovata-toolbox)
- [Lovata.Buddies](https://octobercms.com/plugin/lovata-buddies)

## Installation
To install from the [repository](https://github.com/planetadeleste/oc-api-toolbox), clone it into `plugins/planetadeleste/apitoolbox` and then run `composer update` from your project root in order to pull in the dependencies.

To install it with **Composer**, run `composer require oc-apitoolbox-plugin` from your project root.

## Documentation

### Usage
Coming soon

### Events
Coming soon

### Console commands
**Create a new Api controller class**
Make a new controller for api toolbox. New file will be located in `$author/$plugin/controllers/api/$controllerName.php`
Command: `apitoolbox:create:controller`
Arguments:
| Name | Required | Description |
|--|--|--|
| `plugin` | `true` | The name of the plugin to create. Eg: RainLab.Blog |
| `controller` | `true` | The name of the controller to create. Eg: Posts |

Options:
|Name|Value|Description|
|--|--|--|
| `force` | NONE | Overwrite existing files with generated ones. |
| `model` | String (optional) | The name of the model. Eg: Post |


**Create a new Api resource elements**
Make new files for api resource controller.
New files will be located in

    $author/$plugin/classes/resource/$model/IndexCollection.php
    $author/$plugin/classes/resource/$model/ListCollection.php
    $author/$plugin/classes/resource/$model/ItemResource.php
    $author/$plugin/classes/resource/$model/ShowResource.php

Command: `apitoolbox:create:resource`
Arguments:
| Name | Required | Description |
|--|--|--|
| `plugin` | `true` | The name of the plugin to create. Eg: RainLab.Blog |
| `expansion_plugin` | `true` | The name of the other plugin. Eg: RainLab.Blog |
| `model` | `true` | The name of the model to create. Eg: Product |

Options:
|Name|Value|Description|
|--|--|--|
| `force` | NONE | Overwrite existing files with generated ones. |
| `add-images` | NONE | Add `images` relation. |
| `add-preview-image` | NONE | Add `preview_image` relation. |
